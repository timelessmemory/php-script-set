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

class DownloadImage
{

    public function perform()
    {
        $args = $this->args;

        if (empty($args['dir']) || empty($args['deleteDir']) || empty($args['zipFilenameNoExtension'])) {
            ResqueUtil::log(['status' => 'fail to Download image', 'message' => 'missing params', 'args' => $args]);
            return false;
        }

        $photoInfos = StringUtil::unserialize($args['photoInfos']);

        $dir = $args['dir'];

        $deleteDir = $args['deleteDir'];

        $zipFilenameNoExtension = $args['zipFilenameNoExtension'];

        $zipFilename = $zipFilenameNoExtension . ".zip";

        $zipFilePath = $dir . $zipFilename;

        $this->curlGetMulti($photoInfos, $dir);

        $this->compress($dir, $zipFilePath);

        $hashKey = UploadUtil::upload($zipFilePath, $zipFilename);

        $this->deleteAll($deleteDir);

        if ($hashKey) {
            return true;
        } else {
            return false;
        }
    }

    private function compress($dir, $zipFilePath)
    {
        if (!file_exists($zipFilePath)) {
            $zip = new \ZipArchive();

            if ($zip->open($zipFilePath, \ZIPARCHIVE::CREATE) !== TRUE) {
                throw new BadRequestHttpException(Yii::t('game', 'save_path_error'));
            }

            $op = dir($dir);

            while (false != ($item = $op->read())) {

                if ($item == '.' || $item == '..') {
                    continue;
                }

                $zip->addFile($dir . $item);
            }

            $zip->close();

            $op->close();
        }

        if (!file_exists($zipFilePath)) {
            throw new BadRequestHttpException(Yii::t('game', 'save_path_error'));
        }
    }

    private function curlGetMulti($photoInfos, $dir)
    {
        $urls = [];

        $number = 1;

        foreach ($photoInfos as $photoInfo) {

            $photoUrls = $photoInfo['photoUrl'];

            foreach ($photoUrls as $photoUrl) {

                array_push($urls, array(
                    "url" => $photoUrl,
                    "distributorAccount" => $photoInfo['distributorAccount'],
                    "competitionType" => $photoInfo['competitionType']['text'],
                    "uploadTime" => $photoInfo['uploadTime'],
                    "number" => $number,
                ));

                $number++;
            }
        }

        $conn = array();

        $mh = curl_multi_init();

        foreach ($urls as $i => $url) {

            $conn[$i] = curl_init();

            curl_setopt($conn[$i], CURLOPT_URL, $url['url']);
            curl_setopt($conn[$i], CURLOPT_HEADER, 0);
            curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($conn[$i], CURLOPT_TIMEOUT, 30);

            curl_multi_add_handle($mh, $conn[$i]);
        }

        $active = null;

        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            while (curl_multi_exec($mh, $active) === CURLM_CALL_MULTI_PERFORM);

            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach ($urls as $i => $url) {
            $error = curl_error($conn[$i]);

            $filename = $this->renamePhotoName($url['distributorAccount'], $url['competitionType'], $url['uploadTime'], $url['number']);

            $localPath = $this->localUrl($url['url'], $dir, $filename);

            if (json_encode($error) != '""') {
                file_put_contents($localPath, curlGet($url['url']));
            } else {
                file_put_contents($localPath, curl_multi_getcontent($conn[$i]));
            }

            curl_multi_remove_handle($mh, $conn[$i]);

            curl_close($conn[$i]);
        }

        curl_multi_close($mh);
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
