<?php
require_once __DIR__ . '/functions.php';

class TMDB {
    private $apiKey;
    private $lang;
    private $baseUrl = 'https://api.themoviedb.org/3';

    public function __construct() {
        $this->apiKey = TMDB_API_KEY;
        $this->lang = TMDB_LANG;
    }

    private function request($endpoint, $params = []) {
        $params['api_key'] = $this->apiKey;
        $params['language'] = $this->lang;
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 JayMovies');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
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
