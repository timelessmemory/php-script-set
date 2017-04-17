<?php
namespace backend\modules\game\controllers;

use backend\components\wechat\WechatSdk;
use backend\modules\game\models\ApplyCompetition;
use backend\modules\game\models\CompetitionPhoto;
use backend\modules\member\models\Member;
use backend\modules\ufstrust\models\AgentDistributorRelationship;
use backend\modules\ufstrust\utils\CommonUtil;
use backend\utils\LogUtil;
use yii\web\ServerErrorHttpException;
use yii\web\BadRequestHttpException;
use Yii;
use backend\utils\StringUtil;

/**
*
*/
class CompetitionPhotoController extends BaseController
{
    public $modelClass = "backend\modules\game\models\CompetitionPhoto";
    const QINIU_DOMAIN = 'http://vincenthou.qiniudn.com';
    const DOWNLOAD_PATH = "/imgDownload/";

    //downloadImageService.export 'game-download-image', "/api/game/competition-photo/download-image", {}, false
    public function actionDownloadImage()
    {
        // $params = $this->getParams();

        //test data start
        $photoItems = array();

        $photoItem = array(
            "_id" => "58edf1e1a2f1b115d475a993",
            "year" => "2017",
            "quarter" => "2",
            "accountId" => "58b527d1a2f1b11a5b39cf82",
            "distributorId" => "58d082d3a2f1b155af645dd2",
            "competitionType" => array("text" => "品牌专区"),
            "distributorAccount" => "MS000",
            "distributorName" => "mario",
            "photoUrl" => [
                "http://vincenthou.qiniudn.com/6798002981711491988960.png",
                "http://vincenthou.qiniudn.com/4255884001581491988960.png",
                "http://vincenthou.qiniudn.com/2675677532951491988974.png"
            ],
            "uploadTime" => "2017-04-12 09:22:41"
        );

        array_push($photoItems, $photoItem);

        $photoItem = array(
            "_id" => "58ede81ba2f1b111030a8f32",
            "year" => "2017",
            "quarter" => "2",
            "accountId" => "58b527d1a2f1b11a5b39cf82",
            "distributorId" => "58d082d3a2f1b155af645dd2",
            "competitionType" => array("text" => "品类专区"),
            "distributorAccount" => "YT00579",
            "distributorName" => "times",
            "photoUrl" => [
                "http://vincenthou.qiniudn.com/8799058323211491986459.png",
                "http://vincenthou.qiniudn.com/4736973079271491986459.png"
            ],
            "uploadTime" => "2017-04-12 08:40:59"
        );

        array_push($photoItems, $photoItem);

        $params = array("competitionPhotos" => $photoItems);
        //test data end

        $items = $params["competitionPhotos"];

        $count = count($items);

        if ($count == 0) {
            throw new BadRequestHttpException(Yii::t('game', 'invalid_params'));
        }

        $photoInfos = [];

        $totalPhotosCount = 0;

        foreach ($items as $item) {
            $singleCount = count($item['photoUrl']);

            if ($singleCount != 0) {
                $totalPhotosCount += $singleCount;
                array_push($photoInfos, $item);
            }
        }

        if (count($photoInfos) == 0) {
            throw new BadRequestHttpException(Yii::t('game', 'invalid_params'));
        }

        return $this->download(dirname(__DIR__) . self::DOWNLOAD_PATH, $photoInfos, $totalPhotosCount);
    }

    private function download($path, $photoInfos, $totalPhotosCount) {

        $folderName = strtotime(date('Y-m-d H:i:s')) . mt_rand() . mt_rand();

        $dir = $path . $folderName . DIRECTORY_SEPARATOR;

        $deleteDir = $path . $folderName;

        if (!file_exists($dir) && !mkdir($dir, 0777, true)) {
            throw new BadRequestHttpException(Yii::t('game', 'save_path_error'));
        }

        if ($totalPhotosCount == 1) {

            $photoInfo = $photoInfos[0];

            $url = $photoInfo['photoUrl'][0];

            $filenameNoExtension = $this->renamePhotoName($photoInfo['distributorAccount'], $photoInfo['competitionType']['text'], $photoInfo['uploadTime'], "1");
            $filename = $filenameNoExtension . "." . pathinfo($url, PATHINFO_EXTENSION);

            $downloadArgs = [
                'dir' => $dir,
                'photoInfo' => StringUtil::serialize($photoInfo),
                'deleteDir' => $deleteDir,
                'filename' => $filename,
                'filenameNoExtension' => $filenameNoExtension
            ];

            $jobId = Yii::$app->job->create('backend\modules\game\job\DownloadImageNoCompress', $downloadArgs);

            return ['result' => 'success', 'message' => 'download image', 'data' => ['jobId' => $jobId, 'key' => $filename]];

        } else {
            $zipFilenameNoExtension = Yii::t('game', 'game_display_compress_name') . $totalPhotosCount . Yii::t('game', 'game_display_compress_file') . date('YmdHis');

            $downloadArgs = [
                'dir' => $dir,
                'photoInfos' => StringUtil::serialize($photoInfos),
                'deleteDir' => $deleteDir,
                'zipFilenameNoExtension' => $zipFilenameNoExtension
            ];

            $jobId = Yii::$app->job->create('backend\modules\game\job\DownloadImage', $downloadArgs);

            return ['result' => 'success', 'message' => 'download image', 'data' => ['jobId' => $jobId, 'key' => $zipFilenameNoExtension]];
        }
    }

    private function renamePhotoName($distrbutorAccount, $competitionType, $uploadTime, $number)
    {
        $separator = "_";
        return $distrbutorAccount . $separator . $competitionType . $separator . $uploadTime . $separator . $number;
    }
}
