<?php
/**
 * Sandro Keil (https://sandro-keil.de)
 *
 * @link      http://github.com/sandrokeil/interop-config for the canonical source repository
 * @copyright Copyright (c) 2017-2017 Sandro Keil
 * @license   http://github.com/sandrokeil/interop-config/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Interop\Config\Tool;

use Interop\Config\Exception\InvalidArgumentException;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfig;
use Interop\Config\RequiresConfigId;
use Interop\Config\RequiresMandatoryOptions;

/**
 * Dumps configuration based on factory definition
 *
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 */
class ConfigDumper extends AbstractConfig
{
    const CONFIG_TEMPLATE = <<<EOC
<?php
/**
 * This file is generated by \Interop\Config\Tool\ConfigDumper.
 *
 * @see https://sandrokeil.github.io/interop-config/reference/console-tools.html interop-config documentation
 */ 

EOC;

    /**
     * @var ConsoleHelper
     */
    private $helper;

    public function __construct(ConsoleHelper $helper = null)
    {
        $this->helper = $helper ?: new ConsoleHelper();
    }

    /**
     * @param array $config
     * @param string $className
     * @return array
     * @throws InvalidArgumentException for invalid $className
     */
    public function createConfig(array $config, string $className): array
    {
        $reflectionClass = new \ReflectionClass($className);

        // class is an interface; do nothing
        if ($reflectionClass->isInterface()) {
            return $config;
        }

        $interfaces = $reflectionClass->getInterfaceNames();

        $factory = $reflectionClass->newInstanceWithoutConstructor();
        $dimensions = [];
        $mandatoryOptions = [];
        $defaultOptions = [];

        if (in_array(RequiresConfig::class, $interfaces, true)) {
            $dimensions = $factory->dimensions();
        }

        if (in_array(RequiresConfigId::class, $interfaces, true)) {
            while (true) {
                $configId = $this->helper->readLine('config id or name');
                if ('' !== $configId) {
                    break;
                }
            }
            $dimensions[] = $configId;
        }

        $parent = &$config;

        foreach ($dimensions as $dimension) {
            if (empty($parent[$dimension])) {
                $parent[$dimension] = [];
            }
            $parent = &$parent[$dimension];
        }

        if (in_array(RequiresMandatoryOptions::class, $interfaces, true)) {
            $mandatoryOptions = $this->readMandatoryOption($factory->mandatoryOptions(), $parent);
        }

        if (in_array(ProvidesDefaultOptions::class, $interfaces)) {
            $defaultOptions = $this->readDefaultOption($factory->defaultOptions(), $parent);
        }

        $options = array_replace_recursive(
            $defaultOptions instanceof \Iterator ? iterator_to_array($defaultOptions) : (array)$defaultOptions,
            (array)$mandatoryOptions
        );

        $parent = array_replace_recursive($parent, $options);

        return $config;
    }

    private function readMandatoryOption(iterable $mandatoryOptions, array $config, string $path = ''): array
    {
        $options = [];

        foreach ($mandatoryOptions as $key => $mandatoryOption) {
            if (!is_scalar($mandatoryOption)) {
                $options[$key] = $this->readMandatoryOption(
                    $mandatoryOptions[$key],
                    $config[$key] ?? [],
                    trim($path . '.' . $key, '.')
                );
                continue;
            }
            $previousValue = isset($config[$mandatoryOption]) ? ' (' . $config[$mandatoryOption] . ')' : '';

            $options[$mandatoryOption] = $this->helper->readLine(
                trim($path . '.' . $mandatoryOption, '.') . $previousValue
            );

            if ('' === $options[$mandatoryOption] && isset($config[$mandatoryOption])) {
                $options[$mandatoryOption] = $config[$mandatoryOption];
            }
        }
        return $options;
    }

    private function readDefaultOption(iterable $defaultOptions, array $config, string $path = ''): array
    {
        $options = [];

        foreach ($defaultOptions as $key => $defaultOption) {
            if (!is_scalar($defaultOption)) {
                $options[$key] = $this->readDefaultOption(
                    $defaultOptions[$key],
                    $config[$key] ?? [],
                    trim($path . '.' . $key, '.')
                );
                continue;
            }
            $previousValue = isset($config[$key])
                ? ' (' . $config[$key] . '), provided was <value>' . $defaultOption . '</value>'
                : ' (' . $defaultOption . ')';

            $options[$key] = $this->helper->readLine(trim($path . '.' . $key, '.') . $previousValue);

            if ('' === $options[$key]) {
                $options[$key] = $config[$key] ?? $defaultOption;
            } else {
                $options[$key] = $this->convertToType($options[$key], $defaultOption);
            }
        }
        return $options;
    }

    private function convertToType($value, $originValue)
    {
        switch (gettype($originValue)) {
            case 'boolean':
                return (bool)$value;
            case 'integer':
                return (int)$value;
            case 'double':
                return (float)$value;
            case 'string':
            default:
                return $value;
        }
    }

    public function dumpConfigFile(iterable $config): string
    {
        return 'return ' . $this->prepareConfig($config) . ';';
    }
}
