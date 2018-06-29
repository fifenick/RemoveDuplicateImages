<?php
/**
 *  Remove Duplicate images
 *  Remove Duplicate images from t4 exports, for importing into WordPress
 *  @version 0.1
 */

class RemoveDuplicateImages {

    /**
     * constructer function
     */
    public function __construct() {
        // array to hold all images
        $this->allImgs = array();
    }

    /**
     * Load xml file 
     * @param type $filePath
     * @return type
     */
    private function getFileContent($filePath) {
        return trim(file_get_contents('input/' . $filePath));
    }

    /**
     * Save Contents to the file
     * @param type $filename
     * @param type $content
     */
    private function setFileContent($filename, $content) {
        file_put_contents('output/'.$filename, $content);
    }

    /**
     * Get all the images from the content tag and save them into allimgs
     */
    private function setContentImages($filePath) {
        $contentImg = $this->contentImages($filePath);
        foreach ($contentImg as $imgC) {
            $item = $this->getImgMeta($imgC);
            array_push($this->allImgs, $item);
        }
    }

    // Retrieve all HTML images within XML file
    private function contentImages($filePath) {
        // Define XML file
        $html = $this->getFileContent($filePath);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $tags = $doc->getElementsByTagName('img');
        foreach ($tags as $tag) {
            $content_images[] =  $tag->getAttribute('src');
        }
        return $content_images;
    }

    /**
     * Takes an image url and creates a meta data array
     * @param type $img
     * @return type
     */
    private function getImgMeta($img) {
        $item = array("originalURL" => "", "originalName" => "", "extension" => "", "baseName" => "", "newURL" => "");
        $item['originalURL'] = $img;
        $pos = strrpos($item['originalURL'], '/');
        $item['originalName'] = $pos === false ? $item['originalURL'] : substr($item['originalURL'], $pos + 1);
        $item['extension'] = $this->get_file_extension($item['originalName']);
        $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $item['originalName']);
        $pos = strrpos($withoutExt, '-');
        $end = $pos === false ? $withoutExt : substr($withoutExt, $pos + 1);
        if ( is_numeric($end) ) {
            $item['baseName'] = substr($item['originalName'], 0, strrpos($item['originalName'], '-')) . '.' . $item['extension'];
        } else {
            $item['newURL'] = $item['originalURL'];
        }

        return $item;
    }

    /**
     * get all Featured images from the xml image tag and save them into allimgs
     */
    private function setFeaturedImages($filePath) {
        $xml = $this->getFileContent($filePath);
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->loadXML($xml); // load rss into doc
        $img = array();


        foreach ($doc->getElementsByTagName('item') as $node) {
            if ( $node->getElementsByTagName('image')->item(0) ) {
                $item = $this->getImgMeta($node->getElementsByTagName('image')->item(0)->nodeValue);
                array_push($this->allImgs, $item);
            }
        }
    }

    private function setDuplicatesSearch() {
        echo '<table border="1">';
        echo '<th>Image</th><th>Status</th><th>Change to</th>';
        foreach ($this->allImgs as $img) {
            echo '<tr>';
            // if the image has not got numbers appended to the title it is an original 
            if ( isset($img['newURL']) && strlen($img['newURL']) > 0 ) {
                echo '<td>' . $img['originalURL'] . '</td><td>Original</td><td>' . $img['newURL'] . '</td>';
            } else {
                // The image maybe an duplicate, try to find an early version  
                $re = $this->resolve($img["baseName"], $img['originalName']);
                if ( is_array($re) && !empty($re) ) {
                    // successfully found an early version 
                    $this->setImg($img['originalURL'], $re[0] ["originalURL"]);
                    echo '<td>' . $img['originalURL'] . '</td><td>Duplicate</td><td>' . $re[0] ["originalURL"] . '</td>';
                } else {
                    // can not find an early version 
                    $this->setImg($img['originalURL'], $img['originalURL']);
                    echo '<td>' . $img['originalURL'] . '</td><td>Unresolved</td><td>' . $img['originalURL'] . '</td>';
                }
            }
            echo '</tr>';
        }

        echo '</table>';
    }

    private function setImg($oldURL, $newURL) {
        foreach ($this->allImgs as $img => $v) {
            if ( $v['originalURL'] == $oldURL ) {
                $this->allImgs[$img]['newURL'] = $newURL;
            }
        }
    }

    /**
     * Recursive  function that takes the image baseName ( image title without any version numbers appended) 
     * and searches the image array.  
     * If no image is found the version is incremented and the image array is searched again 
     * until the image name is the same as the original name ( image title with version number appended) .
     * returns false if no image is found
     * @param type $imgTitle
     * @param type $imgOriginal
     * @return boolean
     */
    private function resolve($imgTitle, $imgOriginal) {

        // don't get into an Infinite loop 
        if ( $imgTitle == $imgOriginal | $imgTitle == false ) {
            return false;
        }

        // search the image array
        $result = $this->search($this->allImgs, 'originalName', $imgTitle);

        if ( is_array($result) && empty($result) ) {
            // nothing found 
            // Increment the version in the title
            $nextName = $this->getNextName($imgTitle);

            if ( $nextName == $imgOriginal ) {
                // tried all name variations nothing found.
                return false;
            }
            // search with the new title 
            return $this->resolve($nextName, $imgOriginal);
        } else {
            return $result;
        }
    }

    /**
     *  Function to search array for key word.
     * @param type $array
     * @param type $key
     * @param type $value
     * @return type
     */
    private function search($array, $key, $value) {
        $results = array();
        if ( is_array($array) ) {
            if ( isset($array[$key]) && $array[$key] == $value ) {
                $results[] = $array;
            }
            foreach ($array as $subarray) {
                $results = array_merge($results, $this->search($subarray, $key, $value));
            }
        }
        return $results;
    }

    /**
     * Returns a new image title with an Incremented version number 
     * @param type $imgTitle
     * @return string
     */
    function getNextName($imgTitle) {
        $extension = $this->get_file_extension($imgTitle);
        $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $imgTitle);
        $pos = strrpos($withoutExt, '-');
        $end = $pos === false ? $withoutExt : substr($withoutExt, $pos + 1);
        if ( is_numeric($end) ) {
            $end++;
            $newName = substr($imgTitle, 0, strrpos($imgTitle, '-')) . '-' . $end . '.' . $extension;
        } else {
            $newName = $withoutExt . '-1.' . $extension;
        }
        return $newName;
    }

    /**
     * get the file extension for an file
     * @param type $file_name
     * @return type
     */
    function get_file_extension($file_name) {
        return substr(strrchr($file_name, '.'), 1);
    }

    /**
     * function to find and replace img urls
     * @param type $filePath
     */
    function setFindReplace($filePath){
        $content = $this->getFileContent($filePath);
        foreach ($this->allImgs  as $img){
             $content =  str_ireplace($img['originalURL'],$img['newURL'],$content );
        }
        $this->setFileContent($filePath, $content);
    }
     
    /**
     * main function to remove duplicate images
     * @param type $filePath
     */
    function convert($filePath) {
        // get all the image out of the file add add them to an array
        $this->setContentImages($filePath);
        $this->setFeaturedImages($filePath);

        // Loop over the array searching for duplicates
        $this->setDuplicatesSearch();
       
        // Create a new file with the updated URLS 
        $this->setFindReplace ($filePath);
    }
}