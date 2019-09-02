<?php

namespace App;
use App\Services\MessageService;
use App\Services\UserService;
use Hyperf\DbConnection\Db;
use Swoole\Table;
use Xdapp\Chat\Common\ActionStatus;
use Xdapp\Chat\Common\ErrorCode;
use Xdapp\Chat\Group\CreateGroupRequest;
use Xdapp\Chat\Group\CreateGroupResponse;
use Xdapp\Chat\Message\Message;
use Xdapp\Chat\Server\Cmd;

class ServerEvent {
    public static $connection;
    public static $msgTable;

    public static function beforeStart() {
        self::$connection = new \Swoole\Table(1024);
        self::$connection->column('fd', \Swoole\Table::TYPE_INT, 4);
        self::$connection->column('pid', \Swoole\Table::TYPE_INT, 8);
        self::$connection->create();

        self::$msgTable = new \Swoole\Table(1024);
        self::$msgTable->column('msgId',\Swoole\Table::TYPE_INT, 8);
        self::$msgTable->column('uniqueId',\Swoole\Table::TYPE_INT, 8);
        self::$msgTable->column('fromAccount',\Swoole\Table::TYPE_INT, 8);
        self::$msgTable->column('toSessionId',\Swoole\Table::TYPE_INT, 8);
        self::$msgTable->column('fromNickname',\Swoole\Table::TYPE_STRING, 128);
        //self::$msgTable->column('elements',\Swoole\Table::TYPE_INT, 8);
        self::$msgTable->column('timestamp',\Swoole\Table::TYPE_INT, 8);
        self::$msgTable->create();
    }

    public static function onWorkerStart() {
        /*$data = Db::table('group')->get();
        var_dump($data);*/
    }

    public static function onConnect() {
    }

    public static function onReceive($serv, $fd, $from_id, $data) {
        //echo $fd.'_'."on receiving".PHP_EOL;
        //解析$gateCmd协议
        $params      = self::decode($data);
        $userId      = $params->getUserId();
        $gateId      = $params->getGateId();
        $wrapContent = $params->getContent();

        $wrapCmd = new \Xdapp\Chat\Server\WrapPacket();
        $wrapCmd->mergeFromString($wrapContent);
        $cmd     = $wrapCmd->getCmd();
        $seq     = $wrapCmd->getSeq() + 1;
        $content = $wrapCmd->getContent();
        switch ($cmd) {
            case \Xdapp\Chat\Server\Cmd::Login:
                UserService::login($content, $userId, $seq, $serv, $fd);
                break;
            case \Xdapp\Chat\Server\Cmd::Logout:
                UserService::logout($content, $userId);
                break;
            case \Xdapp\Chat\Server\Cmd::SendC2CMsg:
                MessageService::message($content, $userId, $seq, $serv,$fd);
                break;
            case \Xdapp\Chat\Server\Cmd::GetMessages:
                MessageService::getMessagesRequest($content, $userId, $seq, $serv, $fd);
                break;
            case Cmd::CreateGroup:
                //$reqCreateGroupCmd = new CreateGroupRequest();
                //$reqCreateGroupCmd->mergeFromString($content);
                //if ($userId != $reqCreateGroupCmd->getOwnerAccount()) {
                    //echo "发送建群申请的玩家和群主ID不一致" . PHP_EOL;
                    //return false;
                //}
                //$groupId = $reqCreateGroupCmd->getGroupId();
                //$respCmd = new CreateGroupResponse();
                //$respCmd->setGroupId($groupId);
                //$respCmd->setActStatus(ActionStatus::Ok);
                //$respCmd->setErrCode(ErrorCode::Success);
                //$content = $respCmd->serializeToString();
                //self::sendResponseToPlayer($content, $fd, $serv, $seq, $userId, Cmd::CreateGroupResp);
                //echo "收到建群请求 并成功建群" . PHP_EOL;

                //想把数据存到数据库 还有点问题 先跳过吧
                /*$sql = "select from group where groupId = ?";
                $res = Db::table('group')->get();
                var_dump($res);*/
                //break;
            default:
                echo $cmd . "not found" . PHP_EOL;
                return;
        }
    }

    static function decode($data) {
        $length = @unpack('Slen', $data);
        if (false === $length) {
            echo "no data";
        }
        $buf = substr($data,2);
        $gateCmd = new \Xdapp\Chat\Server\GatewayForwardUserMsg();
        $gateCmd->mergeFromString($buf);
        //var_dump($cmd->serializeToJsonString());
        return $gateCmd;
    }

    static function getToIdMsgArr($uId) {
        $toIdMsgArr = [];
        foreach (ServerEvent::$msgTable as $key => $value) {
            $arr  = explode('_', $key);
            $toId = $arr[1] * 1;
            //判断请求发送缓存消息的玩家和缓存消息要发送的玩家是否一致
            if($uId == $toId) {
                $toIdMsgArr[$key] = $value;
            }
            else {
                continue;
            }
        }
        return $toIdMsgArr;
    }
}