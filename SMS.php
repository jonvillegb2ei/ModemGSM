<?php


namespace App\Libraries\ModemGSM;


class SMS
{

    protected $number;
    protected $content;
    protected $timestamp;
    protected $countryCode;

    public function __construct(string $number = '', string $content = '', int $timestamp = null)
    {
        $this->number = $number;
        $this->content = $content;
        $this->timestamp = is_null($timestamp) ? time() : $timestamp;
    }

    /**
     * @return string
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * @return string
     */
    public function getInternationalNumber(): string
    {
        return $this->internationalisePhoneNumber($this->number);
    }

    /**
     * @return string
     */
    public function getNationalNumber(): string
    {
        return $this->nationalisePhoneNumber($this->number);
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }


    public function __toString()
    {
        return sprintf("SMS receive on %s from %s: \n\t%s\n", date("c", $this->timestamp), $this->getNumber(), $this->getContent());
    }



    /**
     * Get the current country code
     *
     * @return mixed
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * Set country code
     *
     * @param mixed $countryCode
     * @return Modem
     */
    public function setCountryCode($countryCode): SMS
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    /**
     * @param string $phone
     * @return string
     */
    private function internationalisePhoneNumber(string $phone)
    {
        if (!is_null($this->countryCode) and substr($phone, 0, 1) == "0")
            return sprintf("+%s%s", $this->countryCode, substr($phone, 1));
        return $phone;
    }

    /**
     * @param string $phone
     * @return string
     */
    private function nationalisePhoneNumber(string $phone)
    {
        if (!is_null($this->countryCode) and substr($phone, 0, strlen($this->countryCode) + 1) == sprintf("+%s", $this->countryCode))
            return sprintf("0%s", substr($phone, strlen($this->countryCode) + 1));
        return $phone;
    }

}