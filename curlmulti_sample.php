<?php

    /**
     * 並列取得
     *
     * $urlListに設定してある分だけ並列接続をする
     *
     * @param array $urlList 接続先URLリスト
     * @return array $response 取得データ
     */
    public function curlMulti($urlList)
    {
        $response = [];
        $resourceIdList = [];

        try {
            $mh = curl_multi_init();
            foreach ($urlList as $url) {
                $ch = curl_init($url);
                $resourceIdList[] = $ch;
                curl_multi_add_handle($mh, $ch); // マルチハンドルに追加
            }

            $running = null;
            $status = curl_multi_exec($mh, $running); // マルチハンドルの実行
            // $running > 0(実行中)かつ、$status = 0(CURLM_OK)
            while ($running > 0 && $status === CURLM_OK) {
                $select = curl_multi_select($mh, $timeout); // マルチハンドルの状態変化を待つ
                if ($select === -1) { // select に失敗した場合
                    usleep(1000); // phpのバグ対策 参考：http://php.net/manual/ja/function.curl-multi-select.php#115381
                }
                $status = curl_multi_exec($mh, $running); // マルチハンドルのステータス更新
            }

            // 並行取得したリソースをデータフォーマット
            foreach ($resourceIdList as $resourceId) {
                $data = [];
                $data['url'] = curl_getinfo($resourceId, CURLINFO_EFFECTIVE_URL);
                $data['httpStatusCode'] = (int)curl_getinfo($resourceId, CURLINFO_HTTP_CODE);
                $data['errorNo'] = curl_errno($resourceId); // curlMulti使用時にcurl_errno()でエラー番号を取得できない
                $data['errorMsg'] = curl_error($resourceId);
                $data['binary'] = curl_multi_getcontent($resourceId);
                $response[] = $data;

                curl_multi_remove_handle($mh, $resourceId);
                curl_close($resourceId);
            }
            curl_multi_close($mh);
        } catch (Exception $e) {
            throw $e;
        }

        return $response;
    }