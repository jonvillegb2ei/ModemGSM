<?php

namespace App\Libraries\ModemGSM;


use App\Libraries\ModemGSM\Contracts\ISerial;
use App\Libraries\ModemGSM\Exceptions\DeviceAlreadyOpened;
use App\Libraries\ModemGSM\Exceptions\DeviceNotReady;
use App\Libraries\ModemGSM\Exceptions\InvalidBaudrate;
use App\Libraries\ModemGSM\Exceptions\InvalidFlowControl;
use App\Libraries\ModemGSM\Exceptions\InvalidParity;
use App\Libraries\ModemGSM\Exceptions\ReadOnClosedSerialDevice;
use App\Libraries\ModemGSM\Exceptions\UnknownSerialDevice;


/**
 * Class WindowsSerial
 * @package App\Libraries\ModemGSM
 */
class WindowsSerial extends Serial implements ISerial
{
    /**
     * @var null Windows device name
     */
    protected $winDevice = null;

    /**
     * @var array Valid baudrates
     */
    protected $baudrates = [
        110 => 11,
        150 => 15,
        300 => 30,
        600 => 60,
        1200 => 12,
        2400 => 24,
        4800 => 48,
        9600 => 96,
        19200 => 19,
        38400 => 38400,
        57600 => 57600,
        115200 => 115200
    ];


    /**
     * WindowsSerial constructor.
     */
    public function __construct()
    {
        register_shutdown_function([$this, "close"]);
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
     * @throws DeviceAlreadyOpened
     * @throws UnknownSerialDevice
     */
    public function setDevice(string $device): bool
    {

        if ($this->state == self::SERIAL_DEVICE_OPENED) {
            throw new DeviceAlreadyOpened("You must close your device before to set an other one");
        }

        if (preg_match("@^COM(\\d+):?$@i", $device, $matches)
            and $this->_exec(
                exec("mode " . escapeshellarg($device) . " xon=on BAUD=9600")
            ) === 0
        ) {
            $this->device = "\\.com" . $matches[1];
            $this->winDevice = "COM" . $matches[1];
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

        if (!array_key_exists($rate, $this->baudrates)) {
            throw new InvalidBaudrate("Baudrate " . $rate . " is not valid.");
        }

        return $this->_exec(
                "mode " . escapeshellarg($this->winDevice) . " BAUD=" . (int)$this->baudrates[$rate],
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
            self::PARITY_NONE,
            self::PARITY_ODD,
            self::PARITY_EVEN,
        ];

        if (!in_array($parity, $args)) {
            throw new InvalidParity("Parity " . $parity . " is not valid.");
        }

        return $this->_exec(
                "mode " . escapeshellarg($this->winDevice) . " PARITY=" . substr($parity, 0, 1),
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
                "mode " . escapeshellarg($this->winDevice) . " DATA=" . $length,
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
                "mode " . escapeshellarg($this->winDevice) . " STOP=" . $count,
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
            self::FLOW_CONTROL_NONE => "xon=off octs=off rts=on",
            self::FLOW_CONTROL_RTS_CTS => "xon=off octs=on rts=hs",
            self::FLOW_CONTROL_XON_XOFF => "xon=on octs=off rts=on"
        );

        if (!array_key_exists($mode, $modes)) {
            throw new InvalidFlowControl("Flow control " . $mode . " is not valid.");
        }

        return $this->_exec(
                "mode " . escapeshellarg($this->winDevice) . " " . $modes[$mode],
                $out
            ) == 0;
    }

}