<?php

namespace App\Libraries\ModemGSM;




use App\Libraries\ModemGSM\Exceptions\CantCloseSerialDevice;
use App\Libraries\ModemGSM\Exceptions\CantOpenSerialDevice;
use App\Libraries\ModemGSM\Exceptions\CantWriteOnSerialDevice;
use App\Libraries\ModemGSM\Exceptions\DeviceAlreadyOpened;
use App\Libraries\ModemGSM\Exceptions\InvalidOpeningMode;
use App\Libraries\ModemGSM\Exceptions\OpenUnsetDevice;
use App\Libraries\ModemGSM\Exceptions\UnknownOperatingSystem;
use App\Libraries\ModemGSM\Exceptions\WriteOnClosedSerialDevice;

class Serial
{

    protected const SERIAL_DEVICE_NOTSET = 0;
    protected const SERIAL_DEVICE_SET = 1;
    protected const SERIAL_DEVICE_OPENED = 2;

    protected const PARITY_NONE = "none";
    protected const PARITY_ODD = "odd";
    protected const PARITY_EVEN = "even";

    protected const FLOW_CONTROL_NONE = "none";
    protected const FLOW_CONTROL_RTS_CTS = "rts/cts";
    protected const FLOW_CONTROL_XON_XOFF = "xon/xoff";

    protected $state = self::SERIAL_DEVICE_NOTSET;
    protected $device = null;
    protected $handle = null;
    protected $buffer = '';

    protected $autoFlush = true;


    public function _exec($cmd, &$out = null)
    {
        $desc = [
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $proc = proc_open($cmd, $desc, $pipes);

        $ret = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $retVal = proc_close($proc);

        if (func_num_args() == 2) $out = array($ret, $err);
        return $retVal;
    }


    /**
     * @param string $mode
     * @return bool
     * @throws DeviceAlreadyOpened
     * @throws OpenUnsetDevice
     * @throws InvalidOpeningMode
     * @throws CantOpenSerialDevice
     */
    public function open(string $mode = "r+b"): bool
    {
        if ($this->state == self::SERIAL_DEVICE_OPENED) {
            throw new DeviceAlreadyOpened("The device is already opened");
        }

        if ($this->state == self::SERIAL_DEVICE_NOTSET) {
            throw new OpenUnsetDevice("The device must be set before to be open");
        }

        if (!preg_match("@^[raw]\\+?b?$@", $mode)) {
            throw new InvalidOpeningMode("Invalid opening mode : ".$mode.". Use fopen() modes.");
        }

        $this->handle = @fopen($this->device, $mode);

        if ($this->handle == false) {
            $this->handle = null;
            throw new CantOpenSerialDevice("Unable to open the device");
        }

        stream_set_blocking($this->handle, 0);

        $this->state = self::SERIAL_DEVICE_OPENED;

        return true;
    }


    /**
     * @return bool
     * @throws CantCloseSerialDevice
     */
    public function close(): bool
    {
        if ($this->state !== self::SERIAL_DEVICE_OPENED) {
            return true;
        }

        if (!fclose($this->handle)) {
            throw new CantCloseSerialDevice("Unable to close the device");
        }

        $this->handle = null;
        $this->state = self::SERIAL_DEVICE_SET;

        return true;
    }


    /**
     * Sends a string to the device
     *
     * @param string $data
     * @param float $waitForReply time to wait for the reply (in seconds)
     * @return bool
     * @throws CantWriteOnSerialDevice
     * @throws WriteOnClosedSerialDevice
     */
    public function send(string $data, float $waitForReply = 0.1): bool
    {
        $this->buffer .= $data;

        if ($this->autoFlush === true) {
            $this->flush();
        }

        usleep((int) ($waitForReply * 1000000));

        return true;
    }


    /**
     * Flushes the output buffer
     * Renamed from flush for osx compat. issues
     *
     * @return bool
     * @throws CantWriteOnSerialDevice
     * @throws WriteOnClosedSerialDevice
     */
    public function flush(): bool
    {
        if ($this->state !== self::SERIAL_DEVICE_OPENED) {
            throw new WriteOnClosedSerialDevice("Can't write on closed serial device");
        }

        if (fwrite($this->handle, $this->buffer) !== false) {
            $this->buffer = "";
            return true;
        } else {
            $this->buffer = "";
            throw new CantWriteOnSerialDevice("Error while sending message");
        }

    }

    /**
     * @param string $device
     * @return LinuxSerial
     * @throws DeviceAlreadyOpened
     * @throws Exceptions\NoSTTYAvailable
     * @throws Exceptions\UnknownSerialDevice
     * @throws UnknownOperatingSystem
     */
    static public function create(string $device)
    {
        $sysName = php_uname();
        if (substr($sysName, 0, 5) === "Linux") {
            $serial = new LinuxSerial();
        } elseif (substr($sysName, 0, 6) === "Darwin") {
            $serial = new OSXSerial();
        } elseif (substr($sysName, 0, 7) === "Windows") {
            $serial = new WindowsSerial();
        } else {
            throw new UnknownOperatingSystem("Unsupported operating system");
        }
        $serial->setDevice($device);
        return $serial;
    }


}