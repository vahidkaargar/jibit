# جیبیت (Jibit gateway)

پکیج کامپوزر درگاه واسط جیبیت
(Jibit gateway php composer package)

جیبیت به عنوان یکی از بزرگ‌ترین پرداخت‌یار‌های رسمی شاپرک (بانک مرکزی)، با ارائه درگاه پرداخت با قابلیت اتصال به درگاه‌های متنوع و مسیردهی هوشمند بین PSPها در راستای پایداری در سیستم پرداخت و افزایش درآمد در کنار کسب و کارها ایستاده است.

### Compatibility
``php 7.4 and up``

### Installation
```
composer require vahidkaargar/jibit
```

### Request to payment
```php
use vahidkaargar\jibit;

$jibit = new Jibit("API_KEY", "API_SECRET");

$request = $jibit->paymentRequest('AMOUNT_RIAL', 'YOUR_INVOICE_ID', 'MOBILE_NUMBER', 'CALLBACK_URL');

if (!empty($request['pspSwitchingUrl'])) {
    // successful result and redirect to PG
    header('Location: ' . $request['pspSwitchingUrl']);
}
if (!empty($request['errors'])) {
    // fail result and show the error
    echo $request['errors'][0]['code'] . ' ' . $request['errors'][0]['message'];
}
```

### Verify payment
```php
use vahidkaargar\jibit;

if (empty($_POST['amount']) || empty($_POST['purchaseId']) || empty($_POST['status'])) {
    echo 'No data found.';
} else {
    //get data from query string
    $amount = $_POST['amount'];
    $refNum = $_POST['purchaseId'];
    $state = $_POST['status'];

    $jibit = new Jibit("API_KEY", "API_SECRET");

    // Making payment verify
    $request = $jibit->paymentVerify($refNum);
    if (!empty($request['status']) && $request['status'] === 'SUCCESSFUL') {
        //successful result
        echo 'Successful! refNum:' . $refNum;

        //show session detail
        $order = $jibit->getOrderById($refNum);
        if (!empty($order['elements'][0]['pspMaskedCardNumber'])) {
            echo 'payer card pan mask: ' . $order['elements'][0]['pspMaskedCardNumber'];
        }
    } else {
        echo 'Verifying payment has been failed!';
    }
}
```