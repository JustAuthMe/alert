<?php
require_once 'config.dist.php';
require_once 'Redis.php';

function isJamInternal() {
    return isset($_SERVER['HTTP_X_ACCESS_TOKEN']) && $_SERVER['HTTP_X_ACCESS_TOKEN'] === JAM_INTERNAL_API_KEY;
}

header('Content-Type: application/json');
$http_status = '400 Bad Request';
$response = ['status' => 'error', 'message' => 'Error processing the request'];

$redis = new \PHPeter\Redis();
$cache_key = ALERT_CACHE_PREFIX . 'en';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        if (isJamInternal()) {
            if (isset($_POST['alert_type'], $_POST['alert_text'])) {
                if (in_array($_POST['alert_type'], ALERT_TYPES)) {
                    if (!isset($_POST['alert_ttl']) || is_numeric($_POST['alert_ttl'])) {
                        $to_cache = json_encode([
                            'id' => time(),
                            'type' => $_POST['alert_type'],
                            'text' => $_POST['alert_text']
                        ]);
                        $ttl = isset($_POST['alert_ttl']) ? (int) $_POST['alert_ttl'] : ALERT_DEFAULT_TTL;
                        $redis->set($cache_key, $to_cache, $ttl);

                        $response = ['status' => 'success'];
                    } else {
                        $response['message'] = 'Wrong TTL';
                    }
                } else {
                    $response['message'] = 'Wrong alert type';
                }
            } else {
                $response['message'] = 'Alert infos are missing';
            }
        } else {
            $http_status = '401 Unauthorized';
            $response['message'] = 'Authentication failed';
        }
        break;

    case 'DELETE':
        if (isJamInternal()) {
            $redis->del($cache_key);
            $response = ['status' => 'success'];
        } else {
            $http_status = '401 Unauthorized';
            $response['message'] = 'Authentication failed';
        }
        break;

    default:
        $cached = $redis->get($cache_key);
        if ($cached !== false) {
            $response = [
                'status' => 'success',
                'alert' => $cached
            ];

            if (isJamInternal()) {
                $response['alert']->ttl = $redis->ttl($cache_key);
            }
        } else {
            $http_status = '404 Not Found';
            $response['message'] = 'There is currently no ongoing alert';
        }
}

if ($response['status'] == 'success') {
    $http_status = '200 OK';
}

header('HTTP/1.1 ' . $http_status);
echo json_encode($response);