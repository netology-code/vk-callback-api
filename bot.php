<?php

require_once(__DIR__ . '/vendor/autoload.php');

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use ATehnix\VkClient\Client;

define('APP_TOKEN', getenv('APP_TOKEN'));

$api = new Client;
$logger = new Logger('botlog');
$logger->pushHandler(new StreamHandler(__DIR__.'/bot.log', Logger::INFO));

$body = file_get_contents('php://input');
$request = json_decode($body, true);
$logger->info('Request recived', ['request' => $request]);

$groups = json_decode(file_get_contents('groups.json'), true);
$admins = file('admins.conf');

function sendNotification($to, $message) 
{
    global $api;
    $api->request('messages.send', [
        'message' => $message, 
        'user_id' => $to,
    ], '7923fb11ede5caf9bcfe18b7e370178c174685f51e71dadf918a355a3a3a70f7aac4f187572bb2402f91f');
}

function sendNotifications($to, $message) {
    foreach ($to as $uid) {
        if ($uid) {
            sendNotification($uid, $message);    
        }
    }
}

if (!isset($groups[$request['group_id']])) {
    $logger->error('Bad group', ['groups' => $groups]);
    die('Bad group id');
}

$group = $groups[$request['group_id']];
if (isset($request['secret']) && $request['secret'] !== $group['secret']) {
    $logger->error('Bad secret', ['group' => $group]);
    die('Bad secret');
}

switch ($request['type']) {
case 'confirmation':
    echo $group['salt'];
    die();
    break;
case 'message_new':
    $message = $request['object'];
    $resp = $api->request('users.get', ['user_ids' => $message['user_id']]);
    
    $api->request('messages.send', [
        'message' => "Привет, {$resp['response'][0]['first_name']}! Ты написал <{$message['body']}>.", 
        'user_id' => $message['user_id'],
    ], APP_TOKEN);
    
    sendNotifications($admins, "{$resp['response'][0]['first_name']} написал сообщение <{$message['body']}> в группу.");
    
    break;
case 'wall_post_new':
    $post = $request['object'];
    if (0) {
        $api->request('wall.delete', [
            'owner_id'  => $post['owner_id'],
            'post_id'   => $post['id'],
        ], APP_TOKEN);    
    }
    
    $resp = $api->request('users.get', ['user_ids' => $post['from_id']]);
    
    $api->request('messages.send', [
        'message' => "Привет, {$resp['response'][0]['first_name']}! Спасибо за пост. Пиши еще!", 
        'user_id' => $post['from_id'],
    ], APP_TOKEN);
    
    sendNotifications($admins, "{$resp['response'][0]['first_name']} добавил пост <{$post['text']}> в группу.");
    break;
default:
    $logger->info('Other event', [ 'message' => $message ]);
    break;
}

echo 'ok';
