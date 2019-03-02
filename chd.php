<?php
/**

グーグルのマイカレンダーと日本の休日（祝日含む）から
休日か出社日か判定する。
休日の場合は0を返し、出社の場合は1を返す。


グーグルのマイカレンダーに全休と入力すると入力した日は休日と判定され、
グーグルのマイカレンダーに出勤と入力すると入力した日は休日であっても出勤日と判定される。
どちらも登録されてあった場合は出勤日が優先される。

**/

// マイカレンダーの題名で休みとみなすワード
$holiday_words = array(
    '全休',
    '休み',
    '休暇',
    '代休',
);

// マイカレンダーの題名で出社とみなすワード
$workday_words = array(
    '出勤',
    '出社',
    '出張',
    '勤務',
);

$date = new DateTime();
$date->setTimeZone( new DateTimeZone('Asia/Tokyo'));
$google_api_key = ''; // googleカレンダーのAPIキーを取得したものを設定

$calendar_id = urlencode(''); // グーグルのマイカレンダーを作成し対象のカレンダーID取得したものを設定
$result = getGoogleCalendar( $calendar_id, $google_api_key, $date);
if (!empty($result)){
    foreach ($result as $event) {
        foreach ($workday_words as $workday_word) {
            if (strpos($event['summary'], $workday_word) !== false) {
                echo '出社';
                exit(1);
            }
        }
    }

    foreach ($result as $event) {
        foreach ($holiday_words as $holiday_word) {
            if (strpos($event['summary'], $holiday_word) !== false) {
                echo '有給';
                exit(0);
            }
        }
    }
}

// 日本の祝日カレンダーID
$calendar_id = urlencode('ja.japanese#holiday@group.v.calendar.google.com');
$result = getGoogleCalendar( $calendar_id, $google_api_key, $date);
if (!empty($result)){
    echo '祝日';
    exit(0);
}

// 土日判定
if ($date->format('w') == 0 || $date->format('w') == 6) {
    echo '休日';
    exit(0);
}

echo('平日');
exit(1);

function getGoogleCalendar($calendar_id, $google_api_key, $date, $max_results = 100) {
    $param_start = $date->format('Y-m-d') . 'T00:00:00Z'; // イベント取得時刻
    $param_end = $date->format('Y-m-d') . 'T23:59:59Z';
    $url  = 'https://www.googleapis.com/calendar/v3/calendars/' . $calendar_id . '/events';
    $url .= '?key=' . $google_api_key;
    $url .= '&timeMin=' . $param_start;
    $url .= '&timeMax=' . $param_end;
    $url .= '&maxResults=' . $max_results; // イベント取得件数
    $url .= '&orderBy=startTime';
    $url .= '&singleEvents=true';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    curl_close($ch);

    if (empty($result)) {
        return array();
    }

    $json = json_decode($result);

    if (empty($json->items)) {
        return array();
    }

    $holiday_list = array();
    foreach ($json->items as $item) {
        $holiday['start_date'] = $item->start->date;
        $holiday['summary'] = $item->summary;
        $holiday_list[] = $holiday;
    }

    return $holiday_list;
}
