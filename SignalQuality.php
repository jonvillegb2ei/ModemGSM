<?php


namespace App\Libraries\ModemGSM;


class SignalQuality
{

    private $value;

    const LEVEL_MARGINAL = "Marginal";
    const LEVEL_OK = "OK";
    const LEVEL_EXCELLENT = "Excellent";

    public function __construct(float $value)
    {
        $this->value = $value;
    }

    public function getCondition(): string
    {
        if ($this->value < 10) return self::LEVEL_MARGINAL;
        else if ($this->value < 20) return self::LEVEL_OK;
        else return self::LEVEL_EXCELLENT;
    }

    public function getPercent(): float
    {
        return ($this->value / 30) * 100;
    }

    public function getRSSI(): float
    {
        return -113 + (2 * $this->value);
    }

    /**
     * @return float
     */
    public function getValue(): float
    {
        return $this->value;
    }

}