<?php


namespace App\Libraries\ModemGSM;


use App\Libraries\ModemGSM\Exceptions\BadResponse;

class Modem
{

    protected $serial;
    protected $buffer = [];

    /**
     * Modem constructor.
     *
     * @param string $device
     *
     * @throws Exceptions\DeviceAlreadyOpened
     * @throws Exceptions\NoSTTYAvailable
     * @throws Exceptions\UnknownOperatingSystem
     * @throws Exceptions\UnknownSerialDevice
     */
    public function __construct(string $device)
    {
        $this->serial = Serial::create($device);
    }


    /**
     * Read data from serial
     *
     * @param int $count
     * @return string
     * @throws Exceptions\ReadOnClosedSerialDevice
     */
    private function read(int $count = 0): string
    {
        $data = $this->serial->read($count);
        $data = array_values(array_filter(explode("\n", str_replace("\r", "", $data))));
        $this->buffer = array_merge($this->buffer, $data);
        return implode("\n", $data);
    }

    /**
     * Open serial port
     *
     * @param string $mode
     *
     * @return bool
     *
     * @throws Exceptions\CantOpenSerialDevice
     * @throws Exceptions\DeviceAlreadyOpened
     * @throws Exceptions\InvalidOpeningMode
     * @throws Exceptions\OpenUnsetDevice
     */
    public function open(string $mode = "r+b"): bool
    {
        return $this->serial->open($mode);
    }

    /**
     * Close serial port
     *
     * @return bool
     *
     * @throws Exceptions\CantCloseSerialDevice
     */
    public function close(): bool
    {
        return $this->serial->close();
    }

    /**
     * Set serial baudrate
     *
     * @param int $rate
     *
     * @return bool
     *
     * @throws Exceptions\DeviceNotReady
     * @throws Exceptions\InvalidBaudrate
     */
    public function setBaudRate(int $rate): bool
    {
        return $this->serial->setBaudRate($rate);
    }

    /**
     * Set serial parity
     *
     * @param string $parity
     *
     * @return bool
     *
     * @throws Exceptions\DeviceNotReady
     * @throws Exceptions\InvalidParity
     */
    public function setParity(string $parity): bool
    {
        return $this->serial->setParity($parity);
    }

    /**
     * Set serial character length
     *
     * @param int $length
     *
     * @return bool
     *
     * @throws Exceptions\DeviceNotReady
     */
    public function setCharacterLength(int $length): bool
    {
        return $this->serial->setCharacterLength($length);
    }

    /**
     * Set serial stop bits
     *
     * @param float $count
     *
     * @return bool
     *
     * @throws Exceptions\DeviceNotReady
     */
    public function setStopBits(float $count): bool
    {
        return $this->serial->setStopBits($count);
    }

    /**
     * Set serial flow control
     *
     * @param string $mode
     *
     * @return bool
     *
     * @throws Exceptions\DeviceNotReady
     * @throws Exceptions\InvalidFlowControl
     */
    public function setFlowControl(string $mode): bool
    {
        return $this->serial->setFlowControl($mode);
    }

    /**
     * Send command to GSM modem
     *
     * @param string $command
     * @param string $end
     * @param float $waitForReply
     *
     * @return Response
     *
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function command(string $command, string $end = "\r\n", float $waitForReply = 2) : Response
    {
        $this->serial->send($command . $end, $waitForReply);
        $response = $this->read();
        return new Response($response);
    }

    /**
     * Check modem communication
     *
     * @return Response
     *
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function check() : Response
    {
        return $this->command('AT');
    }

    /**
     * Send pin code
     *
     * @param string $pin
     *
     * @return Response
     *
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function setPinCode(string $pin): Response
    {
        return $this->command('AT+CPIN=' . $pin);
    }

    /**
     * Set GSM text mode
     *
     * @return Response
     *
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function setTextMode(): Response
    {
        return $this->command('AT+CMGF=1');
    }


    /**
     * Set SMS center number
     *
     * @param string $center
     *
     * @return Response
     *
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function setSmsCenter(string $center): Response
    {
        return $this->command('AT+CSCA="'.$center.'"');
    }


    /**
     * Send SMS
     *
     * @param SMS $sms
     * @param int $terminate
     *
     * @return Response
     *
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function sendSMS(SMS $sms, int $terminate = 26) : Response
    {
        $this->serial->send("AT+CMGS=\"" . $sms->getInternationalNumber() . "\"\r\n");
        $this->serial->send($sms->getContent());
        $this->serial->send(chr($terminate), 5);
        $response = $this->read();
        return new Response($response);
    }

    /**
     * Get SIM state
     *
     * @return Response
     *
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function getSimState(): Response
    {
        $this->command('AT+CMEE=2');
        return $this->command('AT+CPIN?', "\r\n", 2);
    }

    /**
     * Check if SIM is ready
     *
     * @return bool
     *
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function isReady(): bool
    {
        $response = $this->getSimState();
        return strpos($response->getContent(), '+CPIN: READY') !== False;
    }

    /**
     * Check if SIM require pin code
     *
     * @return bool
     *
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function requirePin(): bool
    {
        $response = $this->getSimState();
        return strpos($response->getContent(), '+CPIN: SIM PIN') !== False;
    }


    /**
     * Get signal quality
     *
     * @return SignalQuality
     *
     * @throws BadResponse
     * @throws Exceptions\CantWriteOnSerialDevice
     * @throws Exceptions\ReadOnClosedSerialDevice
     * @throws Exceptions\WriteOnClosedSerialDevice
     */
    public function getSignalQuality(): SignalQuality
    {
        $response = $this->command('AT+CSQ');

        if ($response->isOk() and preg_match("#\+CSQ: ([0-9,]{1,})#", $response, $match)) {
            return new SignalQuality((float) $match[1]);
        }
        throw new BadResponse("Receive bad response");
    }


    /**
     * Get response buffer
     *
     * @return string
     */
    public function getBuffer(): array
    {
        return $this->buffer;
    }

    /**
     * Receive SMS
     *
     * @return SMS|null
     * @throws Exceptions\ReadOnClosedSerialDevice
     */
    public function receiveSms(): ?SMS
    {
        $data = explode("\n", $this->read());
        if (count($data) > 0) {
            if (preg_match('#\+CMT: "([+0-9]{10,})","","([/0-9]{8}),([:0-9]{8})\+([0-9]+)"#', $data[0], $match)) {
                $content = "";
                if (count($data) > 1) {
                    for($i=1;$i<count($data);$i++) {
                        $content .= $data[$i] . ($i < count($data) - 1 ? "\n" : "");
                    }
                }
                $timestamp = \DateTime::createFromFormat('!y/m/d H:i:s', $match[2]. " " . $match[3])->getTimestamp();
                return new SMS($match[1], $content, $timestamp);
            }
        }
        return null;
    }


}






