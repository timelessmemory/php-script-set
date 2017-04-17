<?php
namespace backend\modules\game\job;

use backend\modules\resque\components\ResqueUtil;
use backend\utils\LogUtil;
use backend\modules\game\utils\UploadUtil;
use backend\utils\StringUtil;
use Yii;
use yii\helpers\FileHelper;
use backend\models\Message;
use yii\web\BadRequestHttpException;

class DownloadImageNoCompress
{

    public function perform()
    {
        $args = $this->args;

        if (empty($args['dir']) || empty($args['deleteDir']) || empty($args['filename'])) {
            ResqueUtil::log(['status' => 'fail to Download image', 'message' => 'missing params', 'args' => $args]);
            return false;
        }

        $photoInfo = StringUtil::unserialize($args['photoInfo']);

        $dir = $args['dir'];

        $deleteDir = $args['deleteDir'];

        $filename = $args['filename'];
        $filenameNoExtension = $args['filenameNoExtension'];

        $url = $photoInfo['photoUrl'][0];

        $localPath = $this->localUrl($url, $dir, $filenameNoExtension);

        file_put_contents($localPath, $this->curlGet($url));

        $hashKey = UploadUtil::upload($localPath, $filename);

        $this->deleteAll($deleteDir);

        if ($hashKey) {
            return true;
        } else {
            return false;
        }
    }

    private function curlGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }

    private function renamePhotoName($distrbutorAccount, $competitionType, $uploadTime, $number)
    {
        $separator = "_";
        return $distrbutorAccount . $separator . $competitionType . $separator . $uploadTime . $separator . $number;
    }

    private function localUrl($url, $dir, $filename)
    {
        return $dir . $filename . "." . pathinfo($url, PATHINFO_EXTENSION);
    }

    private function deleteAll($path)
    {
        $op = dir($path);

        $item = $op->read();

        while (false != $item) {

            if ($item == '.' || $item == '..') {
                $item = $op->read();

                if (false == $item ) {
                    rmdir($path);
                    continue;
                } else {
                    continue;
                }
            }

            unlink($op->path . DIRECTORY_SEPARATOR . $item);

            $item = $op->read();

            if (false == $item) {
                rmdir($path);
            }
        }

        $op->close();
    }
}
