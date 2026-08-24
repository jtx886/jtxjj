<?php
require_once __DIR__ . '/functions.php';

class TMDB {
    private $apiKey;
    private $lang;
    private $baseUrl = 'https://api.themoviedb.org/3';
    private $db;

    public function __construct() {
        $this->apiKey = TMDB_API_KEY;
        $this->lang = TMDB_LANG;
        $this->db = Database::getInstance();
    }

    private function request($endpoint, $params = []) {
        $params['api_key'] = $this->apiKey;
        $params['language'] = $this->lang;
        ksort($params);
        $query = http_build_query($params);
        $cacheKey = md5($endpoint . '?' . $query);

        // 1) 先查数据库缓存
        $cached = $this->db->tmdbCacheGet($cacheKey);
        if (is_array($cached)) return $cached;

        // 2) 发 HTTP 请求（加超时控制，防止 TMDB 连不上时卡死）
        $url = $this->baseUrl . $endpoint . '?' . $query;
        $response = null;

        $timeout = defined('HTTP_TOTAL_TIMEOUT') ? HTTP_TOTAL_TIMEOUT : 10;
        $connectTimeout = defined('HTTP_CONNECT_TIMEOUT') ? HTTP_CONNECT_TIMEOUT : 5;

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 JayMovies');
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            if ($errno || !is_string($response) || $response === '') $response = null;
        }

        if ($response === null && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 JayMovies\r\nAccept: */*\r\n",
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $response = @file_get_contents($url, false, $ctx);
            if (!is_string($response) || $response === '') $response = null;
        }

        if ($response === null) return null;

        $data = json_decode($response, true);
        if (!is_array($data)) return null;

        // 3) 写入数据库缓存（成功缓存1小时，失败结果缓存5分钟避免反复请求）
        $isBad = (isset($data['success']) && $data['success'] === false) || isset($data['status_code']);
        $this->db->tmdbCacheSet($cacheKey, $data, $isBad ? 300 : (defined('TMDB_CACHE_TTL') ? TMDB_CACHE_TTL : 3600));

        return $data;
    }

    public function getTrending($mediaType = 'all', $time = 'week', $page = 1) {
        return $this->request("/trending/$mediaType/$time", ['page' => $page]);
    }

    public function getPopular($mediaType = 'movie', $page = 1) {
        return $this->request("/$mediaType/popular", ['page' => $page]);
    }

    public function getTopRated($mediaType = 'movie', $page = 1) {
        return $this->request("/$mediaType/top_rated", ['page' => $page]);
    }

    public function getNowPlaying($page = 1) {
        return $this->request("/movie/now_playing", ['page' => $page]);
    }

    public function getOnTheAir($page = 1) {
        return $this->request("/tv/on_the_air", ['page' => $page]);
    }

    public function getDetails($mediaType, $id) {
        $result = $this->request("/$mediaType/$id");
        if(!is_array($result)) return null;
        if($mediaType == 'movie') {
            $credits = $this->request("/movie/$id/credits");
            $result['credits'] = $credits;
            $videos = $this->request("/movie/$id/videos");
            $result['videos'] = $videos;
        } else {
            $credits = $this->request("/tv/$id/aggregate_credits");
            $result['credits'] = $credits;
            $videos = $this->request("/tv/$id/videos");
            $result['videos'] = $videos;
        }
        return $result;
    }

    public function getSeasonDetails($tvId, $seasonNumber) {
        return $this->request("/tv/$tvId/season/$seasonNumber");
    }

    public function getEpisodeDetails($tvId, $seasonNumber, $episodeNumber) {
        return $this->request("/tv/$tvId/season/$seasonNumber/episode/$episodeNumber");
    }

    public function search($query, $page = 1) {
        return $this->request('/search/multi', ['query' => $query, 'page' => $page]);
    }

    public function getByGenre($mediaType, $genreId, $page = 1) {
        return $this->request("/discover/$mediaType", [
            'with_genres' => $genreId,
            'sort_by' => 'popularity.desc',
            'page' => $page
        ]);
    }

    public function getGenres($mediaType) {
        $result = $this->request("/genre/$mediaType/list");
        return isset($result['genres']) ? $result['genres'] : [];
    }

    public function getImageUrl($path, $size = 'w500') {
        if(!$path) return '';
        return TMDB_IMG_URL . $size . $path;
    }

    public function getExternalIds($mediaType, $id) {
        return $this->request("/$mediaType/$id/external_ids");
    }
}
?>
