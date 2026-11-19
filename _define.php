<?php

/**
 * @brief YAGB (Yet Another Gallery Builder), a plugin for Dotclear 2
 *
 * Upload a set of photos from the post editor and insert them as a
 * lightweight HTML gallery.
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright effisk
 * @copyright AGPL-3.0
 */
if (isset($this) && is_object($this) && method_exists($this, 'registerModule') && isset($this->id) && is_string($this->id)) {
    $this->registerModule(
        'Yet Another Gallery Builder',                              // Name
        'Upload photos from the post editor and insert them as a gallery', // Description
        'effisk',                                                   // Author
        '1.0.2',                                                    // Version
        [
            'requires'    => [['core', '2.36']],
            'permissions' => 'My',
            'type'        => 'plugin',
            'priority'    => 1010, // after dcLegacyEditor (1000), so its toolbar exists
            'settings'    => [
                'self' => '',
            ],
            'details'     => 'https://github.com/effisk/YAGB',
            'support'     => 'https://github.com/effisk/YAGB/issues',
            'repository'  => 'https://raw.githubusercontent.com/effisk/YAGB/main/dcstore.xml',
        ]
    );
}
