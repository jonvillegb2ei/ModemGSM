<?php

namespace App\Libraries\ModemGSM\Contracts;


interface ISerial
{
    public function setDevice(string $device): bool;

    public function open(string $mode = "r+b"): bool;

    public function close(): bool;

    public function setBaudRate(int $rate): bool;

    public function setParity(string $parity): bool;

    public function setCharacterLength(int $length): bool;

    public function setStopBits(float $count): bool;

    public function setFlowControl(string $mode): bool;

    public function send(string $data, float $waitForReply = 0.1): bool;

    public function read(int $count = 0): string;

    public function flush(): bool;
}