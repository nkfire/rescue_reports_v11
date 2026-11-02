<?php

namespace Nkfire\RescueReports\Utility;

class TestDebugUtility
{
    public function debugItems(array &$config): void
    {
        $config['items'][] = ['Debug funktioniert 🎉', 1234];
    }
}
