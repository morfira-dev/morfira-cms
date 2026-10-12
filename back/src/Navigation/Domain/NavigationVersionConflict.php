<?php
declare(strict_types=1);
namespace Cms\Navigation\Domain;
final class NavigationVersionConflict extends \DomainException { public function __construct() { parent::__construct('The navigation menu was changed by another editor. Reload it before saving again.'); } }
