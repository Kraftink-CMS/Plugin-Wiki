<?php

namespace KraftInk\Plugins\Wiki\Services\Imports;

use App\Kraft\Migration\MappingRegistry;
use KraftInk\Plugins\Wiki\Services\Imports\Azuriom\Mappings;
use KraftInk\Plugins\Wiki\Services\Imports\KraftInk\Mappings as KraftInkMappings;
use KraftInk\Plugins\Wiki\Services\Imports\MineWeb\Mappings as MineWebMappings;

final class ImportMappings
{
    public function register(MappingRegistry $registry): void
    {
        $writer = new Writer();
        new Mappings($writer)->register($registry);
        new MineWebMappings($writer)->register($registry);
        new KraftInkMappings()->register($registry);
    }
}
