#Fuel Payments

This package makes use of PHP-Payments, a generic payments interface supporting multiple gateways, specific to the PHP language, inspired by such projects as Active Merchant.

##Installing

Place in your packages directory.

##Using

To load:

```php
Package::load('Payment');
```
...or simply always_load.

To use as a single instance:

```php
$p = Payment::oneoff_payment('authorize_net', array('cc_number' => 4444444444444444, 'cc_code' => 432, 'amount' => 30.00));

//You'll get a generic response indicating success or failure, along with a message, error code and the raw response from the gateway
var_dump($p);
```

To create multiple instances:
```
$i = Payment::instance('key');
$p = $i->oneoff_payment('authorize_net', array('cc_number' => 444444444444, 'cc_code' => 432, 'amount' => 30.00));

var_dump($p);
```
Full documentation of methods, examples, and other such shits are available in the src/php-payments directory.

##License
Copyright (c) 2011-2012 Calvin Froedge

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
