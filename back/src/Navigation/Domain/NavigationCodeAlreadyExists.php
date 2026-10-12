<?php
declare(strict_types=1);
namespace Cms\Navigation\Domain;
final class NavigationCodeAlreadyExists extends \DomainException { public function __construct(string $code) { parent::__construct(sprintf('A navigation menu with code "%s" already exists.', $code)); } }
