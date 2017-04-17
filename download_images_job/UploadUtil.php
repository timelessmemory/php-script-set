<?php
namespace backend\modules\game\utils;

use Yii;
use yii\helpers\FileHelper;
use backend\utils\LogUtil;
use backend\utils\StringUtil;
use yii\helpers\Json;

class UploadUtil
{
    const HASH_TABLE_NAME = 'wm_download_file_hashs';

    public static function upload($filePath, $key)
    {
        if (!file_exists($filePath)) {
            LogUtil::info(['message' => 'File not exists', 'fileName' => $filePath], 'resque');
            return false;
        }

        LogUtil::info(['message' => 'Begin to check file', 'fileName' => $filePath], 'resque');

        $uploadFile = $key;

        Yii::$app->qiniu->change2Private();

        list($result, $error) = Yii::$app->qiniu->getFileInfo($uploadFile);

        LogUtil::info(['message' => 'End to check file', 'fileName' => $filePath], 'resque');

        $redis = Yii::$app->cache->redis;
        $fileHash = md5_file($filePath);
        $fileValue = $redis->HGET(self::HASH_TABLE_NAME, $uploadFile);

        if (empty($error) && $fileValue == $fileHash) {
            LogUtil::info(['message' => 'File exists in qiniu', 'fileName' => $filePath], 'resque');
            $result = true;
        } else {
            LogUtil::info(['message' => 'Begin to upload file to qiniu', 'fileName' => $filePath], 'resque');

            $result = Yii::$app->qiniu->upload($filePath, $uploadFile, true);

            if (!empty($result->Err)) {
                LogUtil::info(['message' => 'fail to upload file to qiniu', 'result' => json_encode($result), 'fileName' => $filePath], 'resque');
                $result = false;
            } else {
                LogUtil::info(['message' => 'upload file to qiniu successfully', 'result' => json_encode($result), 'fileName' => $filePath], 'resque');
                $redis->HSET(self::HASH_TABLE_NAME, $uploadFile, $fileHash);
            }

            LogUtil::info(['message' => 'End to upload file to qiniu', 'fileName' => $filePath], 'resque');
        }
        return $result;
    }

}
