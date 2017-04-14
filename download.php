<?php
    /*
        使用curl进行下载
        使用zipArchive压缩
        先下载到本地服务器， 客户端下载完成后删除
        图片只有一张时直接下载， 多于一张批量下载并打包为zip包。
    */
    actionDownloadImage();

    function actionDownloadImage()
    {
        $downloadPath = "/imgDownload/";

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
            exit();
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
            exit();
        }

        download(dirname(__DIR__) . $downloadPath, $photoInfos, $totalPhotosCount);
    }

    function download($path, $photoInfos, $totalPhotosCount) {

        $folderName = strtotime(date('Y-m-d H:i:s')) . mt_rand() . mt_rand();

        $dir = $path . $folderName . DIRECTORY_SEPARATOR;

        $deleteDir = $path . $folderName;

        if (!file_exists($dir) && !mkdir($dir, 0777, true)) {
            exit();
        }

        if ($totalPhotosCount == 1) {
            singleDownload($photoInfos[0], $dir, $deleteDir);
        } else {
            multiDownload($photoInfos, $dir, $deleteDir);
        }
    }

    function singleDownload($photoInfo, $dir, $deleteDir) {

        $url = $photoInfo['photoUrl'][0];

        $filename = renamePhotoName($photoInfo['distributorAccount'], $photoInfo['competitionType']['text'], $photoInfo['uploadTime'], "1");

        $localPath = localUrl($url, $dir, $filename);

        file_put_contents($localPath, curlGet($url));

        downloadFile($localPath, $filename . "." . pathinfo($url, PATHINFO_EXTENSION), $deleteDir);
    }

    function multiDownload($photoInfos, $dir, $deleteDir)
    {
        $zipFilename = "images.zip";

        $zipFilePath = $dir . $zipFilename;

        curlGetMulti($photoInfos, $dir);

        compress($dir, $zipFilePath);

        downloadFile($zipFilePath, $zipFilename, $deleteDir);
    }

    function downloadFile($filePath, $filename, $deleteDir)
    {
        if (file_exists($filePath)) {

            $file = fopen($filePath, "r");

            Header("Content-type: application/octet-stream");
            Header("Accept-Ranges: bytes");
            Header("Accept-Length: " . filesize($filePath));
            Header("Content-Disposition: attachment; filename=" . $filename);

            $buffer = 1024;

            while (!feof($file)) {
                $file_data = fread($file, $buffer);
                echo $file_data;
            }

            fclose($file);

            deleteAll($deleteDir);
        } else {
            exit();
        }
    }

    function compress($dir, $zipFilePath)
    {
        if (!file_exists($zipFilePath)) {
            $zip = new \ZipArchive();

            if ($zip->open($zipFilePath, \ZIPARCHIVE::CREATE) !== TRUE) {
                exit('无法打开文件，或者文件创建失败');
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
            exit("无法找到文件");
        }
    }

    function curlGetMulti($photoInfos, $dir)
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

            $filename = renamePhotoName($url['distributorAccount'], $url['competitionType'], $url['uploadTime'], $url['number']);

            $localPath = localUrl($url['url'], $dir, $filename);

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

    function curlGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }

    function renamePhotoName($distrbutorAccount, $competitionType, $uploadTime, $number)
    {
        $separator = "_";
        return $distrbutorAccount . $separator . $competitionType . $separator . $uploadTime . $separator . $number;
    }

    function localUrl($url, $dir, $filename)
    {
        return $dir . $filename . "." . pathinfo($url, PATHINFO_EXTENSION);
    }

    function deleteAll($path)
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