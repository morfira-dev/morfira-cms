"""Reconcile a complete WXR rehearsal against its three CLI reports."""

import argparse
from collections import Counter
import json
from pathlib import Path
import re
import sys
from uuid import UUID
from xml.etree import ElementTree


WORDPRESS_ID = re.compile(r"[1-9][0-9]{0,19}")
ELIGIBLE_STATUSES = {"publish", "draft", "pending", "future"}
WXR_SIZE_LIMIT = 8 * 1024 * 1024


class WxrTreeBuilder(ElementTree.TreeBuilder):
    def doctype(self, name, public_id, system_id):
        raise ValueError("WXR document type declarations are unsupported")


def read_inventory(path):
    with path.open("rb") as source:
        xml = source.read(WXR_SIZE_LIMIT + 1)
    if len(xml) > WXR_SIZE_LIMIT:
        raise ValueError("WXR file exceeds 8 MiB")
    parser = ElementTree.XMLParser(target=WxrTreeBuilder())
    root = ElementTree.fromstring(xml, parser)
    channel = root.find("channel")
    if root.tag != "rss" or channel is None:
        raise ValueError("expected a WXR RSS channel")
    namespaces = [f"{{http://wordpress.org/export/{version}/}}" for version in ("1.0", "1.1", "1.2")]
    namespace = next((item for item in namespaces if channel.find(item + "wxr_version") is not None), None)
    if namespace is None:
        raise ValueError("expected a supported WXR namespace")
    materials = {}
    attachments = set()
    skipped_types = Counter()
    seen = set()
    for item in channel.findall("item"):
        item_id = item.findtext(namespace + "post_id", "").strip()
        if not WORDPRESS_ID.fullmatch(item_id) or item_id in seen:
            raise ValueError("WXR contains an invalid or duplicate item ID")
        seen.add(item_id)
        kind = item.findtext(namespace + "post_type", "").strip()
        if kind == "attachment":
            attachments.add(item_id)
        elif kind in {"page", "post"}:
            status = item.findtext(namespace + "status", "").strip()
            protected = bool(item.findtext(namespace + "post_password", ""))
            materials[item_id] = (kind, status, status in ELIGIBLE_STATUSES and not protected)
        else:
            skipped_types[kind if re.fullmatch(r"[a-z_-][a-z0-9_-]{0,19}", kind) else "(unknown)"] += 1
    return materials, attachments, skipped_types


def index_rows(rows, label, errors):
    if not isinstance(rows, list):
        raise ValueError(f"{label}: expected an array")
    indexed = {}
    for row in rows:
        if not isinstance(row, dict) or not isinstance(row.get("id"), str) or not WORDPRESS_ID.fullmatch(row["id"]):
            raise ValueError(f"{label}: invalid WordPress item ID")
        if not isinstance(row.get("state"), str) or not row["state"]:
            raise ValueError(f"{label}: invalid state for ID {row['id']}")
        if row["id"] in indexed:
            errors.append(f"{label}: duplicate WordPress ID {row['id']}")
        indexed[row["id"]] = row
    return indexed


def compare_ids(index, expected, label, errors):
    missing = sorted(expected - index.keys(), key=int)
    unexpected = sorted(index.keys() - expected, key=int)
    if missing:
        errors.append(f"{label}: missing IDs {', '.join(missing)}")
    if unexpected:
        errors.append(f"{label}: unexpected IDs {', '.join(unexpected)}")
    return {"covered": len(index.keys() & expected), "missing": missing, "unexpected": unexpected}


def diagnostics(rows):
    return [{key: row[key] for key in ("id", "state", "reason") if key in row}
            for row in rows.values() if row["state"] not in {"planned", "imported", "already_imported"}]


def review_images(items, label):
    missing_inline = []
    missing_featured = []
    for item_id, row in items.items():
        images = row.get("images", {})
        if not isinstance(images, dict) or type(images.get("missingInline", 0)) is not int:
            raise ValueError(f"{label}: invalid image report for ID {item_id}")
        if images.get("missingInline", 0) > 0:
            missing_inline.append(item_id)
        if images.get("featured") == "missing":
            missing_featured.append(item_id)
    return missing_inline, missing_featured


def audit(args):
    materials, attachments, skipped_types = read_inventory(args.wxr)
    eligible = {item_id for item_id, (_, _, allowed) in materials.items() if allowed}
    unsupported = materials.keys() - eligible
    reviewed = set(args.allow_skipped)
    errors = []
    if not materials:
        errors.append("WXR contains no pages or posts to rehearse")
    if unsupported - reviewed:
        errors.append(f"Unreviewed unsupported material IDs: {', '.join(sorted(unsupported - reviewed, key=int))}")
    if reviewed - unsupported:
        errors.append(f"Allowed skip IDs are not unsupported in this export: {', '.join(sorted(reviewed - unsupported))}")

    stages = [
        ("dryRun", args.dry_run, {"dry-run"}, "planned"),
        ("apply", args.apply, {"apply"}, "imported"),
        ("replay", args.replay, {"dry-run", "apply"}, "already_imported"),
    ]
    source_key = None
    planned_media = set()
    apply_assets = {}
    reports = {}
    for stage, path, modes, expected_state in stages:
        report = json.loads(path.read_text(encoding="utf-8"))
        if not isinstance(report, dict) or report.get("mode") not in modes or report.get("input") != "wxr":
            raise ValueError(f"{stage}: expected a WXR report for the requested mode")
        if report.get("scope", "all") != "all":
            raise ValueError(f"{stage}: expected a whole-source report")
        if source_key is None:
            source_key = report.get("source")
            if not isinstance(source_key, str) or not source_key:
                raise ValueError("dryRun: missing source key")
        if report.get("source") != source_key:
            errors.append(f"{stage}: source key changed")
        items = index_rows(report.get("items"), f"{stage} materials", errors)
        media = index_rows(report.get("media"), f"{stage} attachments", errors)
        item_counts = Counter(row["state"] for row in items.values())
        if report.get("counts") != dict(item_counts):
            errors.append(f"{stage}: material state counts disagree with item rows")
        if report.get("skippedTypes") != dict(skipped_types):
            errors.append(f"{stage}: skipped-type counts disagree with the WXR inventory")

        draft_ids = set()
        destinations = set()
        for item_id in items.keys() & materials.keys():
            row = items[item_id]
            kind, status, allowed = materials[item_id]
            expected = expected_state if allowed else "unsupported_status"
            if (row.get("type"), row.get("status")) != (kind, status) or row["state"] != expected:
                errors.append(f"{stage}: ID {item_id} expected {kind}/{status}/{expected}, observed {row.get('type')}/{row.get('status')}/{row['state']}")
            if stage == "apply" and allowed and row["state"] == "imported":
                destination = row.get("targetPath")
                if not isinstance(destination, str) or not destination.startswith("/") or destination in destinations:
                    errors.append(f"apply: ID {item_id} has a missing or duplicate destination")
                else:
                    destinations.add(destination)
            if "draft" not in row:
                continue
            draft = row["draft"]
            if stage != "apply" or row["state"] != "imported" or not isinstance(draft, dict):
                errors.append(f"{stage}: ID {item_id} unexpectedly has a draft reference")
                continue
            draft_id = draft.get("id")
            expected_kind = "page" if kind == "page" else "entry"
            try:
                identity = UUID(draft_id) if isinstance(draft_id, str) else None
            except ValueError:
                identity = None
            if identity is None or draft.get("kind") != expected_kind or identity in draft_ids:
                errors.append(f"apply: ID {item_id} has an invalid or duplicate draft identity")
            else:
                draft_ids.add(identity)

        if stage == "dryRun":
            planned_media = {item_id for item_id, row in media.items() if row["state"] == "planned"}
        if stage == "apply":
            apply_assets = {item_id: row.get("assetId") for item_id, row in media.items() if row["state"] == "imported"}
            if planned_media != apply_assets.keys():
                errors.append("apply: imported attachment IDs differ from the dry-run plan")
            for item_id, asset_id in apply_assets.items():
                if not isinstance(asset_id, str) or not asset_id:
                    errors.append(f"apply: imported attachment {item_id} has no asset ID")
        if stage == "replay":
            for item_id, row in media.items():
                if row["state"] in {"planned", "imported"}:
                    errors.append(f"replay: attachment {item_id} would create a new asset")
                if item_id in apply_assets and (row["state"] != "already_imported" or row.get("assetId") != apply_assets[item_id]):
                    errors.append(f"replay: imported attachment {item_id} did not reuse its asset")

        missing_inline, missing_featured = review_images(items, stage)
        reports[stage] = {
            "mode": report["mode"],
            "materialCoverage": compare_ids(items, materials.keys(), f"{stage} materials", errors),
            "attachmentCoverage": compare_ids(media, attachments, f"{stage} attachments", errors),
            "itemStates": dict(item_counts),
            "mediaStates": dict(Counter(row["state"] for row in media.values())),
            "materialDiagnostics": diagnostics(items),
            "mediaDiagnostics": diagnostics(media),
            "draftReferenceCount": len(draft_ids),
            "missingInlineImageIds": missing_inline,
            "missingFeaturedImageIds": missing_featured,
            "redirectOutcomes": dict(Counter(row["redirect"] for row in items.values() if "redirect" in row)),
            "redirectReviewIds": [row["id"] for row in items.values() if row.get("redirect") in {"conflict", "unsupported"}],
        }

    return {
        "source": source_key,
        "inventory": {
            "pageCount": sum(kind == "page" for kind, _, _ in materials.values()),
            "postCount": sum(kind == "post" for kind, _, _ in materials.values()),
            "eligibleIds": sorted(eligible, key=int),
            "unsupportedIds": sorted(unsupported, key=int),
            "attachmentIds": sorted(attachments, key=int),
            "skippedTypes": dict(skipped_types),
        },
        "reports": reports,
        "manualReview": [
            "Verify published legacy status and exact Location against the source inventory.",
            "Review internal links and fragment targets in rendered HTML and headless output.",
            "Review image gaps, block appearance, plugin-specific content, and SEO metadata.",
        ],
        "passed": not errors,
        "errors": errors,
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("wxr", type=Path)
    parser.add_argument("dry_run", type=Path)
    parser.add_argument("apply", type=Path)
    parser.add_argument("replay", type=Path)
    parser.add_argument("--allow-skipped", action="append", default=[], metavar="WORDPRESS_ID",
                        help="acknowledge an unsupported page or post ID; repeat as needed")
    args = parser.parse_args()
    try:
        result = audit(args)
    except (OSError, ValueError, TypeError, ElementTree.ParseError) as error:
        print(f"Could not audit rehearsal: {error}", file=sys.stderr)
        return 2
    print(json.dumps(result, indent=2, sort_keys=True))
    return 0 if result["passed"] else 1


if __name__ == "__main__":
    sys.exit(main())
