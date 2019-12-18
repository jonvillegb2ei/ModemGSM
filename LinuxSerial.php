<?php

namespace App\Libraries\ModemGSM;


use App\Libraries\ModemGSM\Contracts\ISerial;
use App\Libraries\ModemGSM\Exceptions\DeviceAlreadyOpened;
use App\Libraries\ModemGSM\Exceptions\DeviceNotReady;
use App\Libraries\ModemGSM\Exceptions\InvalidBaudrate;
use App\Libraries\ModemGSM\Exceptions\InvalidFlowControl;
use App\Libraries\ModemGSM\Exceptions\InvalidParity;
use App\Libraries\ModemGSM\Exceptions\NoSTTYAvailable;
use App\Libraries\ModemGSM\Exceptions\ReadOnClosedSerialDevice;
use App\Libraries\ModemGSM\Exceptions\UnknownSerialDevice;


/**
 * Class LinuxSerial
 * @package App\Libraries\ModemGSM
 */
class LinuxSerial extends Serial implements ISerial
{


    /**
     * @var array Valid baudrates
     */
    protected $baudrates = [
        110,
        150,
        300,
        600,
        1200,
        2400,
        4800,
        9600,
        19200,
        38400,
        57600,
        115200,
    ];

    /**
     * LinuxSerial constructor.
     *
     * @throws NoSTTYAvailable
     */
    public function __construct()
    {
        if ($this->_exec("stty") === 0) {
            register_shutdown_function([$this, "close"]);
        } else {
            throw new NoSTTYAvailable("No stty available, unable to run.");
        }

        $this->state = self::SERIAL_DEVICE_NOTSET;

    }

    /**
     * Read data from serial device
     *
     * @param int $count
     *
     * @return string
     *
     * @throws ReadOnClosedSerialDevice
     */
    public function read(int $count = 0): string
    {

        if ($this->state !== self::SERIAL_DEVICE_OPENED) {
            throw new ReadOnClosedSerialDevice("Can't write on closed serial device");
        }

        $content = "";

        $i = 0;

        if ($count !== 0) {
            do {
                if ($i > $count) {
                    $content .= fread($this->handle, ($count - $i));
                } else {
                    $content .= fread($this->handle, 128);
                }
            } while (($i += 128) === strlen($content));
        } else {
            do {
                $content .= fread($this->handle, 128);
            } while (($i += 128) === strlen($content));
        }

        return $content;
    }


    /**
     * Set serial device
     *
     * @param string $device
     *
     * @return bool
     *
     * @throws UnknownSerialDevice
     * @throws DeviceAlreadyOpened
     */
    public function setDevice(string $device): bool
    {
        if ($this->state == self::SERIAL_DEVICE_OPENED) {
            throw new DeviceAlreadyOpened("You must close your device before to set an other one");
        }

        if (preg_match("@^COM(\\d+):?$@i", $device, $matches)) {
            $device = "/dev/ttyS" . ($matches[1] - 1);
        }

        if ($this->_exec("stty -F " . escapeshellarg($device)) === 0) {
            $this->device = $device;
            $this->state = self::SERIAL_DEVICE_SET;
            return true;
        }

        throw new UnknownSerialDevice();
    }


    /**
     * Set baudrate
     *
     * @param int $rate
     *
     * @return bool
     *
     * @throws DeviceNotReady
     * @throws InvalidBaudrate
     */
    public function setBaudRate(int $rate): bool
    {

        if ($this->state !== self::SERIAL_DEVICE_SET) {
            throw new DeviceNotReady("Unable to set the baud rate : the device is either not set or opened");
        }

        if (!in_array($rate, $this->baudrates)) {
            throw new InvalidBaudrate("Baudrate " . $rate . " is not valid.");
        }

        return $this->_exec(
                "stty -F " . escapeshellarg($this->device) . " " . (int)$rate,
                $out
            ) == 0;

    }

    /**
     * Set parity
     *
     * @param string $parity
     *
     * @return bool
     *
     * @throws DeviceNotReady
     * @throws InvalidParity
     */
    public function setParity(string $parity): bool
    {

        if ($this->state !== self::SERIAL_DEVICE_SET) {
            throw new DeviceNotReady("Unable to set the baud rate : the device is either not set or opened");
        }

        $args = [
            self::PARITY_NONE => "-parenb",
            self::PARITY_ODD => "parenb parodd",
            self::PARITY_EVEN => "parenb -parodd",
        ];

        if (!array_key_exists($parity, $args)) {
            throw new InvalidParity("Parity " . $parity . " is not valid.");
        }

        return $this->_exec(
                "stty -F " . escapeshellarg($this->device) . " " . $args[$parity],
                $out
            ) == 0;
    }


    /**
     * Set character length
     *
     * @param int $length
     *
     * @return bool
     *
     * @throws DeviceNotReady
     */
    public function setCharacterLength(int $length): bool
    {
        if ($this->state !== self::SERIAL_DEVICE_SET) {
            throw new DeviceNotReady("Unable to set the baud rate : the device is either not set or opened");
        }

        if ($length < 5) {
            $length = 5;
        } elseif ($length > 8) {
            $length = 8;
        }

        return $this->_exec(
                "stty -F " . escapeshellarg($this->device) . " cs" . $length,
                $out
            ) == 0;
    }


    /**
     * Set stop bits
     *
     * @param float $count
     *
     * @return bool
     *
     * @throws DeviceNotReady
     */
    public function setStopBits(float $count): bool
    {
        if ($this->state !== self::SERIAL_DEVICE_SET) {
            throw new DeviceNotReady("Unable to set the baud rate : the device is either not set or opened");
        }

        return $this->_exec(
                "stty -F " . escapeshellarg($this->device) . " " . (($count == 1) ? "-" : "") . "cstopb",
                $out
            ) == 0;
    }


    /**
     * Set flow control
     *
     * @param string $mode
     *
     * @return bool
     *
     * @throws DeviceNotReady
     * @throws InvalidFlowControl
     */
    public function setFlowControl(string $mode): bool
    {
        if ($this->state !== self::SERIAL_DEVICE_SET) {
            throw new DeviceNotReady("Unable to set the baud rate : the device is either not set or opened");
        }

        $modes = array(
            self::FLOW_CONTROL_NONE => "clocal -crtscts -ixon -ixoff",
            self::FLOW_CONTROL_RTS_CTS => "-clocal crtscts -ixon -ixoff",
            self::FLOW_CONTROL_XON_XOFF => "-clocal -crtscts ixon ixoff"
        );

        if (!array_key_exists($mode, $modes)) {
            throw new InvalidFlowControl("Flow control " . $mode . " is not valid.");
        }

        return $this->_exec(
                "stty -F " . escapeshellarg($this->device) . " " . $modes[$mode],
                $out
            ) == 0;
    }

}