<?php
require_once __DIR__ . '/functions.php';

class TMDB {
    private $apiKey;
    private $readToken;
    private $lang;
    private $baseUrl = 'https://api.themoviedb.org/3';
    private $db;

    // API 请求失败时使用的内置兜底假数据，保证页面能秒开（即使 TMDB 服务器连不上）
    private $fallbackEnabled = true;
    private $posterPool = [];

    // ===== 快速失败（短路）机制：防止 TMDB 连不上时每个请求都等满 3 秒超时 =====
    // 原理：任何一次 HTTP 失败后，写入一个 5 分钟的"坏标记"。
    //       后续 5 分钟内所有请求直接跳过 HTTP，立即返回 fallback（<1ms）。
    //       这样就不会出现"一个页面有 5 个请求 × 每个等 3 秒 = 页面卡 15 秒"的悲剧。
    const FAIL_SHORT_TTL = 300;   // 单次失败后 短路 5 分钟
    const FAIL_FILE      = __DIR__ . '/../data/tmdb_down_until.txt';

    /** 判断当前是否处于短路状态（TMDB 已知不可用，跳过 HTTP） */
    private function isCircuitOpen() {
        static $cached = null;
        if ($cached !== null) return $cached;
        $f = self::FAIL_FILE;
        if (file_exists($f)) {
            $until = intval(trim(@file_get_contents($f)));
            if ($until > time()) { $cached = true; return true; }
            @unlink($f);
        }
        $cached = false;
        return false;
    }
    /** 写入短路标记：N 秒内不再尝试 HTTP */
    private function tripCircuit($ttl = self::FAIL_SHORT_TTL) {
        $f = self::FAIL_FILE;
        @file_put_contents($f, (string)(time() + $ttl));
    }
    /** 清除短路标记（恢复请求） */
    public static function resetCircuit() {
        if (file_exists(self::FAIL_FILE)) @unlink(self::FAIL_FILE);
    }

    public function __construct() {
        $this->apiKey    = TMDB_API_KEY;
        $this->readToken = defined('TMDB_READ_TOKEN') ? TMDB_READ_TOKEN : '';
        $this->lang      = TMDB_LANG;
        $this->db        = Database::getInstance();
        $this->fallbackEnabled = defined('HTTP_FALLBACK_ON_FAIL') ? HTTP_FALLBACK_ON_FAIL : true;
    }

    /**
     * 统一 HTTP 请求（curl 优先，失败切 file_get_contents）
     * 严格遵守 HTTP_CONNECT_TIMEOUT / HTTP_TOTAL_TIMEOUT，防止 TMDB 不通导致页面打开半天
     */
    private function httpGet($url, $timeout = null, $connectTimeout = null) {
        // 支持 index.php 通过 $_SERVER 注入单次更小的超时（预算耗尽模式）
        if ($timeout === null) {
            if (!empty($_SERVER['__JM_TOTAL_TIMEOUT__']))    $timeout = (float)$_SERVER['__JM_TOTAL_TIMEOUT__'];
            else                                              $timeout = (int)(defined('HTTP_TOTAL_TIMEOUT') ? HTTP_TOTAL_TIMEOUT : 5);
        }
        if ($connectTimeout === null) {
            if (!empty($_SERVER['__JM_CONNECT_TIMEOUT__'])) $connectTimeout = (float)$_SERVER['__JM_CONNECT_TIMEOUT__'];
            else                                              $connectTimeout = (int)(defined('HTTP_CONNECT_TIMEOUT') ? HTTP_CONNECT_TIMEOUT : 3);
        }
        $response = null;
        $errMsg = '';

        // 1) curl 通道（优先）
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (JayMovies) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
            if ($this->readToken) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->readToken,
                    'Accept: application/json',
                ]);
            }
            // 如果环境有代理，自动使用
            if (!empty($_SERVER['HTTPS_PROXY'])) {
                curl_setopt($ch, CURLOPT_PROXY, $_SERVER['HTTPS_PROXY']);
            } elseif (!empty($_SERVER['HTTP_PROXY'])) {
                curl_setopt($ch, CURLOPT_PROXY, $_SERVER['HTTP_PROXY']);
            }
            $raw = curl_exec($ch);
            if ($raw !== false && is_string($raw) && $raw !== '') {
                $response = $raw;
            } else {
                $errMsg = 'curl error: ' . curl_error($ch);
            }
            curl_close($ch);
        }

        // 2) file_get_contents 通道（兜底）
        if ($response === null && ini_get('allow_url_fopen')) {
            $proxy = '';
            if (!empty($_SERVER['HTTPS_PROXY']))      $proxy = $_SERVER['HTTPS_PROXY'];
            elseif (!empty($_SERVER['HTTP_PROXY']))   $proxy = $_SERVER['HTTP_PROXY'];
            $ctx_opts = [
                'http' => [
                    'method'  => 'GET',
                    'header'  => ($this->readToken
                            ? "Authorization: Bearer {$this->readToken}\r\nAccept: application/json\r\n"
                            : "Accept: application/json\r\n")
                        . "User-Agent: Mozilla/5.0 (JayMovies)\r\n",
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ];
            if ($proxy) {
                $p = parse_url($proxy);
                if (!empty($p['host'])) {
                    $ctx_opts['http']['proxy'] = 'tcp://' . $p['host'] . ':' . (isset($p['port']) ? $p['port'] : 80);
                    $ctx_opts['http']['request_fulluri'] = true;
                }
            }
            $ctx = stream_context_create($ctx_opts);
            $raw = @file_get_contents($url, false, $ctx);
            if (is_string($raw) && $raw !== '') $response = $raw;
            elseif ($errMsg === '') $errMsg = 'file_get_contents failed';
        }

        return [$response, $errMsg];
    }

    /**
     * 统一 TMDB 请求：数据库缓存 → 【快速短路判断】→ HTTP（严格超时）→ 失败标记短路 → 无缓存也返回 null 让上层 fallback
     */
    private function request($endpoint, $params = [], $useCache = true, $ttl = null) {
        $params['api_key']  = $this->apiKey;
        $params['language'] = $this->lang;
        ksort($params);
        $query = http_build_query($params);
        $cacheKey = md5($endpoint . '?' . $query);

        if ($useCache) {
            $cached = $this->db->tmdbCacheGet($cacheKey);
            if (is_array($cached)) return $cached;
            // 记录"进行中"的短期空缓存：并发请求只放行第一个，其他等 250ms 再查缓存（避免 TMDB 不通时多次等待超时叠加）
            $lockKey = "lock:$cacheKey";
            $lock = $this->db->fetchOne("SELECT expire_at FROM tmdb_cache WHERE cache_key = ?", [$lockKey]);
            if ($lock && (int)$lock['expire_at'] > time()) {
                usleep(250000);
                $cached2 = $this->db->tmdbCacheGet($cacheKey);
                if (is_array($cached2)) return $cached2;
            } else {
                try {
                    if ($this->db->getDriver() === 'mysql') {
                        $this->db->query("INSERT IGNORE INTO tmdb_cache (cache_key, cache_value, expire_at, created_at) VALUES (?, '', ?, ?)",
                            [$lockKey, time() + 6, time()]);
                    } else {
                        $this->db->query("INSERT OR IGNORE INTO tmdb_cache (cache_key, cache_value, expire_at, created_at) VALUES (?, '', ?, ?)",
                            [$lockKey, time() + 6, time()]);
                    }
                } catch (Throwable $e) { /* ignore lock insert error */ }
            }
        }

        // 【快速短路】：如果已知 TMDB 最近 5 分钟连不上，直接跳过 HTTP 请求（避免每次都等 3 秒超时）
        if ($this->isCircuitOpen()) {
            return null;
        }

        $url = $this->baseUrl . $endpoint . '?' . $query;
        list($response, $errMsg) = $this->httpGet($url);

        if ($response === null || $response === '') {
            // 失败！打开短路开关：5 分钟内后续请求不再发 HTTP（立即 fallback），彻底解决卡死问题
            $this->tripCircuit();
            @file_put_contents(__DIR__ . '/../data/tmdb_error.log',
                '[' . date('Y-m-d H:i:s') . "] $url : $errMsg (circuit opened for ".self::FAIL_SHORT_TTL."s)\n", FILE_APPEND);
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->tripCircuit(60);   // json 失败也短路 60 秒
            @file_put_contents(__DIR__ . '/../data/tmdb_error.log',
                '[' . date('Y-m-d H:i:s') . "] $url : json_decode failed, resp_len=" . strlen($response) . "\n", FILE_APPEND);
            return null;
        }
        $isBad = (isset($data['success']) && $data['success'] === false) || isset($data['status_code']);
        if ($useCache) {
            $this->db->tmdbCacheSet($cacheKey, $data, $isBad ? 120 : ($ttl === null ? TMDB_CACHE_TTL : $ttl));
        }
        return $data;
    }

    // ================= 兜底假数据（TMDB 完全连不上时用） =================
    private function genTitle($seed, $type) {
        $moviePool = ['星际战士','深海迷航','雾都迷案','永恒之刃','风之国度','龙城传说','山海奇谭','银河守卫','末世英雄','暗夜追凶','山河岁月','江湖故人','长安十二时辰','流浪地球3','封神2'];
        $tvPool    = ['夜空中最亮的星','山河锦绣','繁花似锦','暗河传','剑王朝第二季','重生之都市修仙','大宋少年志','长相思2','庆余年3','狂飙2','我的阿勒泰','尘封十三载'];
        $pool = $type === 'tv' ? $tvPool : $moviePool;
        return $pool[abs(crc32($seed)) % count($pool)];
    }
    private function genPoster($seed) {
        $n = 1 + abs(crc32($seed)) % 5;
        return "/assets/img/poster$n.jpg";
    }
    private function genFallbackList($count, $type = 'movie', $seed = '') {
        $res = [];
        for ($i = 1; $i <= $count; $i++) {
            $id = 10000 + abs(crc32($seed . $i)) % 90000;
            $res[] = [
                'id'            => $id,
                'media_type'    => $type,
                'title'         => $type === 'movie' ? $this->genTitle("$seed$i", 'movie') : null,
                'name'          => $type === 'tv'    ? $this->genTitle("$seed$i", 'tv')    : null,
                'vote_average'  => 7 + (abs(crc32("$seed$i")) % 30) / 10,
                'release_date'  => (2020 + ($i % 6)) . '-' . sprintf('%02d',1+($i%12)) . '-' . sprintf('%02d',1+($i%28)),
                'first_air_date'=> (2019 + ($i % 6)) . '-' . sprintf('%02d',1+($i%12)) . '-' . sprintf('%02d',1+($i%28)),
                'poster_path'   => '/fallback-poster-' . (1 + $i%5) . '.jpg',
                'backdrop_path' => '/fallback-backdrop-' . (1 + $i%3) . '.jpg',
                'overview'      => '这是一部精彩的' . ($type==='movie'?'电影':'剧集') . '，情节跌宕起伏，引人入胜。',
                'genre_ids'     => [28, 12, 14],
                'popularity'    => 500 - $i,
            ];
        }
        return ['results' => $res, 'total_pages' => 1, 'total_results' => $count, 'page' => 1];
    }

    public function getTrending($mediaType = 'all', $time = 'week', $page = 1) {
        $r = $this->request("/trending/$mediaType/$time", ['page' => $page], true, 1800);
        if (is_array($r) && isset($r['results'])) return $r;
        if (!$this->fallbackEnabled) return ['results' => []];
        return $this->genFallbackList(12, ($mediaType === 'tv' ? 'tv' : 'movie'), "trend$mediaType$time$page");
    }
    public function getPopular($mediaType = 'movie', $page = 1) {
        $r = $this->request("/$mediaType/popular", ['page' => $page], true, 1800);
        if (is_array($r) && isset($r['results'])) return $r;
        if (!$this->fallbackEnabled) return ['results' => []];
        return $this->genFallbackList(20, $mediaType, "pop$mediaType$page");
    }
    public function getTopRated($mediaType = 'movie', $page = 1) {
        $r = $this->request("/$mediaType/top_rated", ['page' => $page], true, 1800);
        if (is_array($r) && isset($r['results'])) return $r;
        if (!$this->fallbackEnabled) return ['results' => []];
        return $this->genFallbackList(20, $mediaType, "top$mediaType$page");
    }
    public function getNowPlaying($page = 1) {
        $r = $this->request("/movie/now_playing", ['page' => $page], true, 1800);
        if (is_array($r) && isset($r['results'])) return $r;
        if (!$this->fallbackEnabled) return ['results' => []];
        return $this->genFallbackList(12, 'movie', "np$page");
    }
    public function getOnTheAir($page = 1) {
        $r = $this->request("/tv/on_the_air", ['page' => $page], true, 1800);
        if (is_array($r) && isset($r['results'])) return $r;
        if (!$this->fallbackEnabled) return ['results' => []];
        return $this->genFallbackList(12, 'tv', "ota$page");
    }

    public function getDetails($mediaType, $id) {
        $cacheKey = "details_{$mediaType}_{$id}_{$this->lang}";
        $cached = $this->db->tmdbCacheGet($cacheKey);
        if (is_array($cached)) return $cached;
        $result = $this->request("/$mediaType/$id", [], false);
        if (!is_array($result)) {
            if (!$this->fallbackEnabled) return null;
            $title = $this->genTitle("d$mediaType$id", $mediaType);
            return [
                'id' => intval($id),
                'title' => $mediaType === 'movie' ? $title : null,
                'name'  => $mediaType === 'tv'    ? $title : null,
                'overview' => '暂无详情（TMDB 连接失败，稍后自动恢复）',
                'poster_path'   => '/fallback-poster-' . (1+$id%5) . '.jpg',
                'backdrop_path' => '/fallback-backdrop-' . (1+$id%3) . '.jpg',
                'vote_average'  => 7.8,
                'release_date'  => date('Y-m-d', time() - 86400 * 365),
                'first_air_date'=> date('Y-m-d', time() - 86400 * 365),
                'runtime'       => 120,
                'credits'       => ['cast'=>[],'crew'=>[]],
                'videos'        => ['results'=>[]],
                'seasons'       => $mediaType === 'tv' ? [
                    ['season_number'=>1,'name'=>'第1季','poster_path'=>'','overview'=>'','air_date'=>date('Y-m-d'),'episode_count'=>12]
                ] : [],
            ];
        }
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
        if (is_array($cached)) return $cached;
        $result = $this->request("/tv/$tvId/season/$seasonNumber", [], false);
        if (!is_array($result) || !isset($result['episodes'])) {
            if (!$this->fallbackEnabled) return ['episodes' => []];
            $eps = [];
            for ($i = 1; $i <= 12; $i++) {
                $eps[] = [
                    'episode_number' => $i,
                    'name'           => '第 ' . $i . ' 集',
                    'still_path'     => '',
                    'air_date'       => date('Y-m-d', time() - 86400 * (13 - $i)),
                    'vote_average'   => 7.5 + (($i * 3) % 20) / 10,
                    'overview'       => '精彩剧情，不容错过。',
                    'runtime'        => 45,
                ];
            }
            return ['episodes' => $eps, 'name' => "第 {$seasonNumber} 季", 'overview' => '暂无简介',
                    'air_date' => date('Y-m-d'), 'poster_path' => ''];
        }
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
        $r = $this->request('/search/multi', [
            'query'         => $query,
            'page'          => $page,
            'include_adult' => 'false',
        ], true, 900);
        if (is_array($r) && isset($r['results'])) return $r;
        if (!$this->fallbackEnabled) return ['results' => []];
        return $this->genFallbackList(12, 'movie', "q" . $query . $page);
    }

    public function getByGenre($mediaType, $genreId, $page = 1) {
        $r = $this->request("/discover/$mediaType", [
            'with_genres' => $genreId,
            'sort_by'     => 'popularity.desc',
            'page'        => $page,
        ], true, 1800);
        if (is_array($r) && isset($r['results'])) return $r;
        if (!$this->fallbackEnabled) return ['results' => []];
        return $this->genFallbackList(20, $mediaType, "g$genreId" . $mediaType . $page);
    }

    public function getGenres($mediaType) {
        static $g = [];
        if (isset($g[$mediaType])) return $g[$mediaType];
        $result = $this->request("/genre/$mediaType/list", [], true, 86400 * 7);
        if (!is_array($result) || !isset($result['genres'])) {
            $fallback = [
                'movie' => [
                    ['id'=>28,'name'=>'动作'],['id'=>35,'name'=>'喜剧'],['id'=>10749,'name'=>'爱情'],
                    ['id'=>878,'name'=>'科幻'],['id'=>53,'name'=>'悬疑'],['id'=>18,'name'=>'剧情'],
                    ['id'=>12,'name'=>'冒险'],['id'=>16,'name'=>'动画'],['id'=>27,'name'=>'恐怖'],
                ],
                'tv' => [
                    ['id'=>10759,'name'=>'动作冒险'],['id'=>35,'name'=>'喜剧'],['id'=>18,'name'=>'剧情'],
                    ['id'=>10765,'name'=>'科幻奇幻'],['id'=>16,'name'=>'动画'],['id'=>80,'name'=>'犯罪'],
                    ['id'=>10751,'name'=>'家庭'],['id'=>9648,'name'=>'悬疑'],
                ],
            ];
            $g[$mediaType] = $fallback[$mediaType] ?? [];
            return $g[$mediaType];
        }
        $g[$mediaType] = $result['genres'];
        return $g[$mediaType];
    }

    public function getImageUrl($path, $size = 'w500') {
        if(!$path) return '';
        // 如果是内置 fallback 图片（/fallback- 开头）直接返回
        if (strpos($path, '/fallback-') === 0) return '';
        if (strpos($path, '/assets/') === 0) return $path;
        return TMDB_IMG_URL . $size . $path;
    }

    public function getExternalIds($mediaType, $id) {
        return $this->request("/$mediaType/$id/external_ids");
    }
}
?>
