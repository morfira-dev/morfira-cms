# ADR 0006: Immutable image pipeline

## Status

Accepted.

## Decision

The Media module treats every uploaded image as untrusted input. It determines the
actual format from the file bytes, fully decodes the image, and re-encodes it as
WebP. The pipeline does not publish the uploaded original and does not copy its
metadata. JPEG, PNG and WebP inputs are accepted up to 10 MiB.

Each upload creates a new immutable `MediaAsset` and a set of responsive variants.
Variant URLs contain the asset identity and their bytes never change at that URL.
Replacing an image means creating another asset; an existing asset is never
overwritten in place. Page Image blocks reference the asset identifier and store
their own required alt text, with the asset's default alt text used only as an
authoring convenience.

SVG is excluded from this pipeline. SVG is active XML content rather than a decoded
raster image and can contain scripts, external references and other XSS-capable
features. Supporting it later requires a separate, explicitly reviewed sanitizer
and delivery policy; changing a file extension or trusting its declared MIME type
is not sufficient.

## Consequences

- public images can use long-lived immutable caching without invalidation races;
- metadata and executable payloads from raster uploads are not served back to users;
- public HTML and headless snapshots can safely retain asset references because an
  asset's URLs and bytes are stable;
- uploads cost CPU and additional storage for responsive variants;
- animated images and original-file downloads are not preserved by this first slice;
- media deletion must not break published snapshots, so physical reclamation needs
  a later reference-aware retention policy.
