<?php
/**
 * Sake
 *
 * @link      http://github.com/sandrokeil/interop-config for the canonical source repository
 * @copyright Copyright (c) 2015 Sandro Keil
 * @license   http://github.com/sandrokeil/interop-config/blob/master/LICENSE.txt New BSD License
 */

namespace Interop\Config;

/**
 * Class MandatoryOptionsInterface
 *
 * Use this interface in a factory which extends from AbstractConfigurableFactory to check for required options
 * automatically.
 */
interface MandatoryOptionsInterface
{
    /**
     * Returns a list of mandatory options which must be available
     *
     * @return array List with options
     */
    public function getMandatoryOptions();
}