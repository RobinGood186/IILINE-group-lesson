<?php
require __DIR__ . '/vendor/autoload.php';

// Путь к файлу ключа сервисного аккаунта
$googleAccountKeyFilePath = __DIR__ . '/service_key.json';
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $googleAccountKeyFilePath);
$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->addScope(['https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/spreadsheets']);
$service = new Google_Service_Sheets($client);
// ID таблицы
$spreadsheetId = '***'; // Добавить

// Текущее время минус сутки для фильтра
date_default_timezone_set('Etc/GMT-5');
$dateTi = new DateTime(date('d-m-Y'));
$dateTi = ($dateTi->format('U') - 86400);


$subdomain = '***'; // добавить
$link = 'https://' . $subdomain . '.amocrm.ru/api/v4/events?filter[created_at]=' . $dateTi . '&with=lead_name'; //Формируем URL для запроса
$access_token = file_get_contents('tokenAmoCRM.txt');
$headers = [
    'Authorization: Bearer ' . $access_token
];

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
curl_setopt($curl, CURLOPT_URL, $link);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
$logfile = __DIR__ . "/JsonRequest.txt";
$file = @fopen($logfile, "w");
curl_setopt($curl, CURLOPT_FILE, $file);
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
$out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);
$code = (int)$code;
$errors = [
    400 => 'Bad request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not found',
    500 => 'Internal server error',
    502 => 'Bad gateway',
    503 => 'Service unavailable',
];

try {
    if ($code < 200 || $code > 204) {
        throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
    }
} catch (\Exception $e) {
    die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
}

$data = json_decode(file_get_contents(__DIR__ . "/JsonRequest.txt"), true);
$file1 = date('d-m-Y') . '.txt';
print_r($data);
foreach ($data['_embedded']['events'] as $data) {
    file_put_contents($file1, 'Время: ' . date('d-m-Y H:i', $data['created_at']) . " " .
        'Автор: ' . $data['account_id'] . " " .
        'Тип объекта: ' . $data['type'] . " " .
        'ID объекта: ' . $data['_embedded']['entity']['id'] . " " .
        'Имя объекта: ' . $data['_embedded']['entity']['name'] . " " .
        "\r\n", FILE_APPEND);
}
$values = [
    [file_get_contents($file1)],
];
$range = 'Лист1!A1';
$ValueRange = new Google_Service_Sheets_ValueRange();
$ValueRange->setMajorDimension('COLUMNS');
$ValueRange->setValues($values);
$options = ['valueInputOption' => 'USER_ENTERED'];
$service->spreadsheets_values->append($spreadsheetId, $range, $ValueRange, $options);