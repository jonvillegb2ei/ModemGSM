#PHP Library for GSM modem (Beta version)


## Usage


### Open serial device

```php

$modem = new Modem("/dev/ttyACM0");

$modem->setBaudRate(115200);
$modem->setParity("none");
$modem->setCharacterLength(8);
$modem->setStopBits(1);
$modem->setFlowControl("none");

$modem->open();

```


### Set pin code

```php

if ($modem->requirePin()) {
    $response = $modem->setPinCode('1234');
    print($response->isError() ? " [-] Can't register pin code.\n" : " [+] Pin code registered.\n");
} else print(" [+] Pin code not required.\n");

```
 
 
 ### Send SMS
 
 ```php

$response = $modem->setTextMode();
print($response->isError() ? " [-] Can't set text mode.\n" : " [+] Modem is in text mode.\n");

$response = $modem->setSmsCenter('+33695000695');
print($response->isError() ? " [+] Can't set SMS center number.\n" : " [+] SMS center number set.\n");
 

if ($modem->isReady()) {
    
    $response = $modem->sendSMS(
        (new SMS('06XXXXXXXX', 'Hello word'))->setCountryCode(CountryCode::France)
    );
    
    print($response->isOK() ? " [+] SMS send.\n" : " [+] Can't send SMS.\n");
}
 ```


### Receive SMS

```php

$start = time();
while(time() - $start < 120) {
    $sms = $modem->receiveSms();
    if ($sms) {
        printf(" [+] %s", $sms);
    }
    sleep(5);
}

```