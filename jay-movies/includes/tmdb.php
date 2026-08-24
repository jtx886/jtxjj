<?php
require_once __DIR__ . '/functions.php';

class TMDB {
    private $apiKey;
    private $readToken;
    private $lang;
    private $baseUrl = 'https://api.themoviedb.org/3';
    private $db;

    public function __construct() {
        $this->apiKey    = TMDB_API_KEY;
        $this->readToken = defined('TMDB_READ_TOKEN') ? TMDB_READ_TOKEN : '';
        $this->lang      = TMDB_LANG;
        $this->db        = Database::getInstance();
    }

    /**
     * 统一 TMDB 请求：带本地数据库缓存，大幅提升详情/分季加载速度
     */
    private function request($endpoint, $params = [], $useCache = true, $ttl = null) {
        $params['api_key']  = $this->apiKey;
        $params['language'] = $this->lang;
        ksort($params); // 保证同组参数生成相同 key
        $query = http_build_query($params);
        $cacheKey = md5($endpoint . '?' . $query);
        if ($useCache) {
            $cached = $this->db->tmdbCacheGet($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $url = $this->baseUrl . $endpoint . '?' . $query;

        $response = null;
        $errMsg = '';
        // ---- 策略1: curl（带超时和重试）----
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (JayMovies) AppleWebKit/537.36');
            if ($this->readToken) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->readToken,
                    'Accept: application/json',
                ]);
            }
            $tries = 0;
            while ($tries < 2) {
                $tries++;
                $raw = curl_exec($ch);
                if ($raw !== false && is_string($raw) && $raw !== '') {
                    $response = $raw;
                    break;
                }
                usleep(150000); // 150ms 重试
            }
            if ($response === null) {
                $errMsg = 'curl error: ' . curl_error($ch);
            }
            curl_close($ch);
        }
        // ---- 策略2: file_get_contents ----
        if ($response === null && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => ($this->readToken
                            ? "Authorization: Bearer {$this->readToken}\r\nAccept: application/json\r\n"
                            : "Accept: application/json\r\n")
                        . "User-Agent: Mozilla/5.0 (JayMovies)\r\n",
                    'timeout' => 15,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            if (is_string($raw) && $raw !== '') {
                $response = $raw;
            } else if ($errMsg === '') {
                $errMsg = 'file_get_contents failed';
            }
        }

        if ($response === null || $response === '') {
            @file_put_contents(__DIR__ . '/../data/tmdb_error.log',
                '[' . date('Y-m-d H:i:s') . "] $url : $errMsg\n", FILE_APPEND);
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return null;
        }
        // 缓存空结果防止短时间重复击穿，但 TTL 更短
        $isBad = (isset($data['success']) && $data['success'] === false) || isset($data['status_code']);
        if ($useCache) {
            $this->db->tmdbCacheSet($cacheKey, $data, $isBad ? 300 : ($ttl === null ? TMDB_CACHE_TTL : $ttl));
        }
        return $data;
    }

    public function getTrending($mediaType = 'all', $time = 'week', $page = 1) {
        $r = $this->request("/trending/$mediaType/$time", ['page' => $page], true, 1800);
        return is_array($r) ? $r : ['results' => []];
    }

    public function getPopular($mediaType = 'movie', $page = 1) {
        $r = $this->request("/$mediaType/popular", ['page' => $page], true, 1800);
        return is_array($r) ? $r : ['results' => []];
    }

    public function getTopRated($mediaType = 'movie', $page = 1) {
        $r = $this->request("/$mediaType/top_rated", ['page' => $page], true, 1800);
        return is_array($r) ? $r : ['results' => []];
    }

    public function getNowPlaying($page = 1) {
        $r = $this->request("/movie/now_playing", ['page' => $page], true, 1800);
        return is_array($r) ? $r : ['results' => []];
    }

    public function getOnTheAir($page = 1) {
        $r = $this->request("/tv/on_the_air", ['page' => $page], true, 1800);
        return is_array($r) ? $r : ['results' => []];
    }

    public function getDetails($mediaType, $id) {
        $cacheKey = "details_{$mediaType}_{$id}_{$this->lang}";
        $cached = $this->db->tmdbCacheGet($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $result = $this->request("/$mediaType/$id", [], false);
        if (!is_array($result)) return null;
        if ($mediaType == 'movie') {
            $credits = $this->request("/movie/$id/credits", [], false);
            if (is_array($credits)) $result['credits'] = $credits;
            $videos = $this->request("/movie/$id/videos", [], false);
            if (is_array($videos)) $result['videos'] = $videos;
        } else {
            $credits = $this->request("/tv/$id/aggregate_credits", [], false);
            if (is_array($credits)) $result['credits'] = $credits;
            $videos = $this->request("/tv/$id/videos", [], false);
            if (is_array($videos)) $result['videos'] = $videos;
        }
        $this->db->tmdbCacheSet($cacheKey, $result);
        return $result;
    }

    public function getSeasonDetails($tvId, $seasonNumber) {
        $cacheKey = "season_{$tvId}_s{$seasonNumber}_{$this->lang}";
        $cached = $this->db->tmdbCacheGet($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $result = $this->request("/tv/$tvId/season/$seasonNumber", [], false);
        if (!is_array($result)) return ['episodes' => []];
        $this->db->tmdbCacheSet($cacheKey, $result);
        return $result;
    }

    public function getEpisodeDetails($tvId, $seasonNumber, $episodeNumber) {
        $cacheKey = "ep_{$tvId}_s{$seasonNumber}_e{$episodeNumber}_{$this->lang}";
        $cached = $this->db->tmdbCacheGet($cacheKey);
        if (is_array($cached)) return $cached;
        $result = $this->request("/tv/$tvId/season/$seasonNumber/episode/$episodeNumber");
        if (is_array($result)) $this->db->tmdbCacheSet($cacheKey, $result);
        return $result;
    }

    public function search($query, $page = 1) {
        // 搜索不缓存或短缓存
        $r = $this->request('/search/multi', [
            'query'          => $query,
            'page'           => $page,
            'include_adult'  => 'false',
        ], true, 900);
        return is_array($r) ? $r : ['results' => []];
    }

    public function getByGenre($mediaType, $genreId, $page = 1) {
        $r = $this->request("/discover/$mediaType", [
            'with_genres' => $genreId,
            'sort_by'     => 'popularity.desc',
            'page'        => $page,
        ], true, 1800);
        return is_array($r) ? $r : ['results' => []];
    }

    public function getGenres($mediaType) {
        static $g = [];
        if (isset($g[$mediaType])) return $g[$mediaType];
        $result = $this->request("/genre/$mediaType/list", [], true, 86400 * 7);
        $g[$mediaType] = isset($result['genres']) ? $result['genres'] : [];
        return $g[$mediaType];
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
