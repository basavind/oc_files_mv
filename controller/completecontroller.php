<?php
/**
 * ownCloud - files_mv
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author eotryx <mhfiedler@gmx.de>
 * @copyright eotryx 2015
 */

namespace OCA\Files_Mv\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IServerContainer;
use OCP\IL10N;

class CompleteController extends Controller {
	/** @var \OC\IL10N */
	private $l;
	private $storage;

	public function __construct($AppName,
								IRequest $request,
								IL10N $l,
								$UserFolder){
		parent::__construct($AppName, $request);
		$this->storage = $UserFolder;
		$this->l = $l;
	}
	/**
	 * provide a list of directories based on the $startDir excluding all directories listed in $file(;sv)
	 * @param string $file - semicolon separated filenames
	 * @param string $startDir - Dir where to start with the autocompletion
	 * @return JSON list with all directories matching
	 *
	 * @NoAdminRequired
	 */
	public function index($file, $StartDir){
		$curDir = $StartDir;
		$files = $this->fixInputFiles($file);
		$dirs = array();

		// fix curDir, so it always start with leading /
		if(empty($curDir)) $curDir = '/';
		else {
			if(strlen($curDir)>1 && substr($curDir,0,1)!=='/'){
				$curDir = '/'.$curDir;
			}
		}
		if(!$this->storage->nodeExists($curDir)){
			// user is writing a longer directory name, so assume the base directory instead and set directory starting letters
			$pathinfo = pathinfo($curDir);
			$curDir = $pathinfo['dirname'];
			if($curDir == ".") $curDir = "";
		}
		if(!($this->storage->nodeExists($curDir)
			&& $this->storage->get($curDir)->getType()===\OCP\Files\FileInfo::TYPE_FOLDER
			)
		){ // node should exist and be a directory, otherwise something terrible happened
			return array("status"=>"error","message"=>$this->l->t('No filesystem found'));
		}
		if(dirname($files[0])!=="/" && dirname($files[0])!==""){
			$dirs[] = '/';
		}
		$patternFile = '!('. implode(')|(',$files) .')!';
		if($curDir!="/" && !preg_match($patternFile,$curDir)) $dirs[] = $curDir;

        $fileDir = dirname($files[0]);
		return $this->getSiblingDirsFor($fileDir); // Return only sibling dirs
	}

	/**
	 * clean Input param $files so that it is returned as an array where each file has a full path
	 * @param String $files
	 * @return array
	 */
	private function fixInputFiles($files){
		$files = explode(';',$files);
		if(!is_array($files)) $files = array($files); // files can be one or many
		$rootDir = dirname($files[0]).'/';//first file has full path
		// expand each file in $files to full path to the user root directory
		for($i=0,$len=count($files); $i<$len; $i++){
			if($i>0) $files[$i] = $rootDir.$files[$i];
			if(strpos($files[$i],'//')!==false){
				$files[$i] = substr($files[$i],1); // drop leading slash, because there are two slashes
			}
		}
		return $files;
	}

	/**
	 * Returns only sibling directories for given one.
	 *
	 * @param String $path - base path for siblings search
	 *
	 * @return array - sibling directories paths without given one
     */
    private function getSiblingDirsFor($path) {
        if ($path === '/') {
            return $this->extractDirsFrom($path);
        }

        if(substr($path,-1)=='/') $path = substr($path,0,-1); //remove ending '/'
		$parentPath = substr($path, 0, strrpos($path, '/'));

        return $this->extractDirsFrom($parentPath, $path);
	}

    /**
     * Extracts all directories from given path.
     *
     * @param             $path
     * @param String|null $exclude
     *
     * @return array
     */
    private function extractDirsFrom($path, $exclude = null) {
        $dirs = array();
        $dirEntries = $this->storage->get($path)->getDirectoryListing();
        foreach ($dirEntries as $entry) {
            if($entry->getType()!==\OCP\Files\FileInfo::TYPE_FOLDER) continue;

            $entryPath = $path.'/'.$entry->getName();
            if($entry->isUpdateable() && $entryPath != $exclude){
                $dirs[] =  $entryPath;
            }
        }
        return $dirs;
    }
}
