<?php
declare(strict_types=1);
namespace Cms\Navigation\Domain;
final class NavigationMenuNotFound extends \DomainException { public function __construct() { parent::__construct('Navigation menu not found.'); } }
