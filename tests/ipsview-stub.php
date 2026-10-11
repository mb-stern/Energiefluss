<?php

declare(strict_types=1);

class IPSModuleStrict
{
    public array $messages = [];
    public bool $sendSucceeds = true;
    private array $properties = [];
    private static array $attributes = [];

    public function __construct(public int $InstanceID) {}
    public function Create(): void {}
    public array $removedHooks = [];
    protected function UnregisterHook(string $hook): bool { $this->removedHooks[] = $hook; return true; }
    protected function SetVisualizationType(int $type): void {}
    protected function RegisterPropertyString(string $name, string $value): void { $this->properties[$name] = $value; }
    protected function RegisterPropertyInteger(string $name, int $value): void { $this->properties[$name] = $value; }
    protected function RegisterPropertyBoolean(string $name, bool $value): void { $this->properties[$name] = $value; }
    protected function ReadPropertyString(string $name): string { return $this->properties[$name]; }
    protected function ReadPropertyInteger(string $name): int { return $this->properties[$name]; }
    protected function ReadPropertyBoolean(string $name): bool { return $this->properties[$name]; }
    protected function RegisterAttributeString(string $name, string $value): void { self::$attributes[$this->InstanceID][$name] ??= $value; }
    protected function ReadAttributeString(string $name): string { return self::$attributes[$this->InstanceID][$name]; }
    protected function WriteAttributeString(string $name, string $value): void { self::$attributes[$this->InstanceID][$name] = $value; }
    protected function UpdateVisualizationValue(mixed $value): bool { $this->messages[] = $value; return $this->sendSucceeds; }
    protected function LogMessage(string $message, int $level): void { throw new RuntimeException($message); }
    public function setProperty(string $name, mixed $value): void { $this->properties[$name] = $value; }
}

$testVariables = [];
function IPS_VariableExists(int $id): bool { return array_key_exists($id, $GLOBALS['testVariables']); }
function IPS_ObjectExists(int $id): bool { return false; }
function GetValue(int $id): mixed { return $GLOBALS['testVariables'][$id]; }
function GetValueFormatted(int $id): string { return (string) GetValue($id); }

