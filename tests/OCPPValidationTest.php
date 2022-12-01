<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class OCPPValidationTest extends TestCaseSymconValidation
{
    public function testValidateOCPP(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateOCPPDeviceModule(): void
    {
        $this->validateModule(__DIR__ . '/../OCPP Charging Point');
    }

    public function testValidateOCPPConfiguratorModule(): void
    {
        $this->validateModule(__DIR__ . '/../OCPP Configurator');
    }

    public function testValidateOCPPSplitterModule(): void
    {
        $this->validateModule(__DIR__ . '/../OCPP Splitter');
    }
}