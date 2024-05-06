<?php

namespace ProcityDev\DataAnalyser;

class DataAnalyser {
    
    protected $data = array();

    public function __construct(string $filePath, $type = 'csv')
    {
        // Load Records from CVS File
        $records = array();

        if ($type == 'json') {
            $records = json_decode(file_get_contents($filePath), true);
        }else{
            $i = 0;
            $handle = @fopen($filePath, "r");
            if ($handle) {
                while (($row = fgetcsv($handle, 4096)) !== false) {
                    if (empty($fields)) {
                        $fields = $row;
                        continue;
                    }
                    foreach ($row as $k=>$value) {
                        if(isset($fields[$k])){
                            $records[$i][$fields[$k]] = $value;
                        }
                    }
                    $i++;
                }
                if (!feof($handle)) {
                    echo "Error: unexpected fgets() fail\n";
                }
                fclose($handle);
            }
        }
        
        $this->data = $records;
    }

    public function print(int $size = null) : array {
        if ($size != null && $size < count($this->data)) {
            return $this->data = array_slice($this->data, 0, $size);
        }else{
            return $this->data;
        }
    }

    public function limit($size = null) {
        if ($size != null) {
            $this->data = array_slice($this->data, 0, $size);
        }
    }

    public function count() {
        return count($this->data);
    }

    public function merge(DataAnalyser $dataArray, $field, $localField = 'id')  {
        $records = array();
        foreach ($dataArray->print() as  $data) {
            if (isset($data[$field])) {
                $record = $this->findData($data[$field], $localField);
                if((isset($data['cast']) && $data['cast'] != null) && (isset($data['crew']) && $data['crew'] != null)){
                    $record['cast'] = json_decode($data['cast'], true);
                    $record['crew'] = json_decode($data['crew'], true);
                    $record[$field] = json_decode($data[$field], true);
                    if ($record['cast'] !== null && $record['crew'] !== null) {
                        array_push($records, $record);
                    }
                }
            }
        }
        $this->data = $records;
    }

    private function findData($value = '', $field = 'id'){
        foreach ($this->data as $key => $data) {
            if (isset($data[$field]) && $data[$field] == $value) {
                return $data;
            }
        }
        return null;
    }

    public function addPosters(){
        foreach ($this->data as $key => $data) {
            $imageBaseUrl = 'https://image.tmdb.org/t/p/';
            $imageSize = 'w500'; // 'original';
            $posters = $this->getPoster($data['movie_id']);
            $this->data[$key]['backdrop_path'] = $posters['backdrop_path'];
            $this->data[$key]['poster_path'] = $posters['poster_path'];
        }
    }

    public function getPoster(string | int $id) {
        // Http Client
        $apiKey = 'efcc816d2e1b314d01117fcd7978e2ce';
        $options = [
            'headers' => [
                'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJlZmNjODE2ZDJlMWIzMTRkMDExMTdmY2Q3OTc4ZTJjZSIsInN1YiI6IjY2Mjg0ZWZkNjNkOTM3MDE2NDczOGNiZCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.9TpRTb2VVGu3Ac33U6N8rwOJqHaEuemQbSxTWI-TWMk',
                'Accept'     => 'application/json'
            ]
        ];

        // Make request
        // $url = 'https://api.themoviedb.org/3/movie/' . $id . '/images';
        // $response = $client->get('https://api.themoviedb.org/3/movie/19995?api_key=' . $apiKey . '&language=en-US');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.themoviedb.org/3/movie/' . $id . '?api_key=' . $apiKey . '&language=en-US');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function filter(array $filter) {
        $records = array();
        foreach ($this->data as $key => $data) {
            $record = array();
            foreach ($filter as $field) {
                $record[$field] = isset($data[$field]) ? $data[$field] : null;
            }
            array_push($records, $record);
        }
        $this->data = $records;
    }

    public function cleanData(){
        $records = array();
        foreach ($this->data as $key => $data) {
            $noNullField = true;
            foreach ($data as $key => $value) {
                if(!(isset($data[$key])) || $data[$key] == null || $data[$key] == ''){
                    $noNullField = false;
                }
            }
            
            if ($noNullField) {
                array_push($records, $data);
            }
        }
        $this->data = $records;
    }

    public function stripArrayToString(string $field){
        foreach ($this->data as $key => $record) {
            if (isset($record[$field])) {
                $data = $record[$field];
                $data = is_array($data) ? $data : json_decode($data, true);
                $stringify = '';
                $no_data = count($data);
                foreach ($data as $index => $value) {
                    $stringify .= $value['name'];
                    if ($index < ($no_data - 1)) {
                        $stringify .= ', ';
                    }
                }
                $this->data[$key][$field] = $stringify;
            }
        }
    }

    public function objectContentToArray(string $field, $restrictTo = null){
        foreach ($this->data as $key => $record) {
            if (isset($record[$field])) {
                $data = $record[$field];
                $data = is_array($data) ? $data : json_decode($data, true);
                $newData = array();
                if ($data != null) {
                    $no_data = count($data);
                    foreach ($data as $index => $value) {
                        array_push($newData, $value['name']);
                        if ($restrictTo !== null){
                            if ($restrictTo == $value['job']) {
                                $crewMember = $value['name'];
                            }
                        }
                    }

                    // Save the new data
                    if ($restrictTo !== null) {
                        $this->data[$key][$restrictTo] = $crewMember;
                        unset($this->data[$key][$field]);
                    }else{
                        $this->data[$key][$field] = $newData;
                    }
                }
            }
        }
    }

    public function objectContentToArrayObject(string $field, array $restrictTo = [], array $arrayKeys = []){
        foreach ($this->data as $key => $record) {
            if (isset($record[$field])) {
                $data = $record[$field];
                $data = is_array($data) ? $data : json_decode($data, true);
                $newData = array();
                if ($data != null) {
                    $no_data = count($data);
                    $colected = array();
                    foreach ($data as $index => $value) {
                        foreach ($restrictTo as $key => $job) {
                            if ($value[$arrayKeys[$key]] == $restrictTo[$key]) {
                                array_push($colected, [$arrayKeys[$key] => $job, 'name' => $value['name']]);
                            }
                        }
                    }


                    
                    $this->data[$key][$field] = $colected;
                }
            }
        }
    }

    public function castStringToArray(string $fieldName, $restrictTo = null, bool $useObject = false){
        foreach ($this->data as $key => $record) {
            if (isset($record[$fieldName])) {
                $data = $record[$fieldName];
                // $data = str_replace('\'', '\"', $data);
                // $data = stripslashes($data);
                $data = json_decode($this->sanitize($data), true);

                return $data;

                // $data = is_array($data) ? $data : json_decode($data);
                $this->data[$key][$fieldName] = $data;
            }
        }
    }

    private function sanitize($inputData){
        // Replacing single quotes inside square brackets with double quotes
        $outputData = preg_replace_callback('/\[([^\]]+)\]/', function($matches) {
            return str_replace("'", '"', $matches[0]);
        }, $inputData);

        // Replacing double quotes outside square brackets with single quotes
        $outputData = preg_replace('/(?<=\s|^)"(?=\[)/m', "'", $outputData);
        $outputData = preg_replace('/(?<=\])"(?=\s|$)/m', "'", $outputData);

        $outputData =  preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $outputData);

        return $outputData;
    }

    public function cleanDataField(string $field){
        $data = $this->data[0][$field];
        $data = json_encode(str_replace('\'', '"', str_replace(' ', '', trim(str_replace('`', '\'', $data)))), JSON_PRETTY_PRINT);
        return json_decode(stripslashes($data), true);
    }

    public function save($filePath, $format = 'json') {
        if ($format == 'json') {
            file_put_contents($filePath, json_encode($this->data, JSON_PRETTY_PRINT));
        }
    }
    
}
