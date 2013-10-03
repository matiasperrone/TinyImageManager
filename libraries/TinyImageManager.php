<?php

require 'Image_Toolbox.class.php';
require 'exifReader.php';

class TinyImageManager
{
	public $dir;
	public $firstAct = false;
	public $folderAct = false;
	public $ALLOWED_IMAGES;
	public $ALLOWED_FILES;
	public $path;
	public $SID;
	public $errors;
	public $config;

	/**
	 * Diseñador
	 *
	 * @return TinyImageManager
	 */
	function __construct($config = array())
	{
		header('Content-Type: text/html; charset=utf-8');
		ob_start("ob_gzhandler");

		if (!is_array($config)) $config = array();
		$this->setConfig($config);
		$action = (array_key_exists('action', $_POST) ? $_POST['action'] : null);
		switch ($action)
		{
			case 'SID':
				exit($this->SID);
			case 'newfolder':
			case 'showtree':
			case 'showpath':
			case 'showdir':
			case 'uploadfile':
			case 'delfile':
			case 'delfolder':
			case 'renamefile':
				$action = '_'.$action;
				exit($this->$action());
			default:
		}
	}

	private function _newfolder()
	{
		$result = array();
		$dir = $this->AccessDir($_POST['path'], $_POST['type']);
		if( $dir )
		{
			if( preg_match('/[a-z0-9-_]+/sim', $_POST['name']) )
			{
				if( is_dir($dir.'/'.$_POST['name']) )
				{
					$result['error'] = $this->errors['This dir already contains'];
				}
				else
				{
					if( mkdir($dir.'/'.$_POST['name']) )
					{
						$result['tree'] = $this->DirStructure('images', 'first', $dir.'/'.$_POST['name']);
						$result['tree'] .= $this->DirStructure('files', 'first', $dir.'/'.$_POST['name']);
						$result['addr'] = $this->DirPath($_POST['type'],
								 $this->AccessDir($_POST['path'].'/'.$_POST['name'], $_POST['type']));
						$result['error'] = '';
					}
					else
					{
						$result['error'] = $this->errors['Error creating dir'];
					}
				}
			}
			else
			{
				$result['error'] = $this->errors['Dir name can only contain letters, numbers, dashes and underscores'];
			}
		}
		else
		{
			$result['error'] = $this->errors['Access denied'];
		}

		echo "{'tree':'{$result['tree']}', 'addr':'{$result['addr']}', 'error':'{$result['error']}'}";
	}

	private function _showtree()
	{
		if( !isset($_POST['path']) )
			$_POST['path'] = '';
		if( !isset($_POST['type']) )
			$_POST['type'] = '';

		if( $_POST['path'] == '/' )
			$_POST['path'] = '';

		if( isset($_POST['default']) && isset($this->path) )
			$path = $this->path;
		else
			$path = $this->path = $_POST['path'];

		if( $_POST['type'] == 'files' )
			$this->firstAct = true;
		if( $_POST['type'] == 'files' )
			echo $this->DirStructure('images', 'first');
		else
			echo $this->DirStructure('images', 'first', $this->AccessDir($path, 'images'));
		if( $_POST['type'] == 'files' )
			$this->firstAct = false;
		if( $_POST['type'] == 'images' )
			echo $this->DirStructure('files', 'first');
		else
			echo $this->DirStructure('files', 'first', $this->AccessDir($path, 'files'));
	}

	private function _showpath()
	{
		if( isset($_POST['default']) && isset($this->path) )
			$path = $this->path;
		else
			$path = $this->path = $_POST['path'];

		echo $this->DirPath($_POST['type'], $this->AccessDir($path, $_POST['type']));
	}

	private function _showdir()
	{
		if( isset($_POST['default']) && isset($this->path) )
			$path = $this->path;
		else
			$path = $this->path = $_POST['path'];

		echo $this->ShowDir($path, $_POST['pathtype']);
	}

	private function _uploadfile()
	{
		echo $this->UploadFile($_POST['path'], $_POST['pathtype']);
	}

	private function _delfile()
	{
		if( is_array($_POST['md5']) )
		{
			foreach( $_POST['md5'] as $k => $v )
			{
				$this->DelFile($_POST['pathtype'], $_POST['path'], $v, $_POST['filename'][$k], true);
			}
			echo $this->ShowDir($_POST['path'], $_POST['pathtype']);
		}
		else
		{
			echo $this->DelFile($_POST['pathtype'], $_POST['path'], $_POST['md5'], $_POST['filename'], true);
		}
	}

	private function _delfolder($action)
	{
		echo $this->DelFolder($_POST['pathtype'], $_POST['path']);
	}

	private function _renamefile($action)
	{
		echo $this->RenameFile($_POST['pathtype'], $_POST['path'], $_POST['filename'], $_POST['newname']);
	}

	private function _errors($lang)
	{
		switch(strtolower(substr($lang, 0, 2)))
		{
			case 'es':
				return array(
							'This dir already contains' => 'Esta carpeta ya contiene',
							'Error creating dir' => 'Ocurrió un error al crear la carpeta',
							'Dir name can only contain letters, numbers, dashes and underscores' => 'El nombre del directorio solo puede contener letras, numeros y guiones',
							'Access denied' => 'Acceso denegado',
							'You can not delete a root dir!' => 'No se puede eliminar el directorio raiz!',
							'As long the dir contains subfolders, it can\'t be removed.' => 'No se puede eliminar una carpeta que contenga subcarpetas.',
							'Error deleting dir' => 'Ocurrió un error al borrar la carpeta'
						);
			case 'fr':
				return array(
							'This dir already contains' => 'Ce dossier contient déjà des',
							'Error creating dir' => 'Erreur lors de la création de dossiers',
							'Dir name can only contain letters, numbers, dashes and underscores' => 'Nommez le dossier ne peut contenir que des lettres, des chiffres, des tirets et caractères de soulignement',
							'Access denied' => 'Accès refusé',
							'You can not delete a root dir!' => 'Vous ne pouvez pas supprimer le dossier racine!',
							'As long the dir contains subfolders, it can\'t be removed.' => 'Tant que le dossier contient des sous-dossiers, il ne peut pas être supprimé.',
							'Error deleting dir' => 'Erreur de suppression du dossier'
						);
			case 'ru':
				return array(
							'This dir already contains' => 'Такая папка уже есть',
							'Error creating dir' => 'Ошибка создания папки',
							'Dir name can only contain letters, numbers, dashes and underscores' => 'Название папки может содержать только латинские буквы, цифры, тире и знак подчеркивания',
							'Access denied' => 'Отказ в доступе',
							'You can not delete a root dir!' => 'Нельзя удалять корневую папку!',
							'As long the dir contains subfolders, it can\'t be removed.' => 'Пока папка содержит вложенные папки, она не может быть удалена.',
							'Error deleting dir' => 'Ошибка удаления папки'
						);
			case 'en':
				return = array(
							'This dir already contains' => 'This dir already contains',
							'Error creating dir' => 'Error creating dir',
							'Dir name can only contain letters, numbers, dashes and underscores' => 'Dir name can only contain letters, numbers, dashes and underscores',
							'Access denied' => 'Access denied',
							'You can not delete a root dir!' => 'You can not delete a root dir!',
							'As long the dir contains subfolders, it can\'t be removed.' => 'As long the dir contains subfolders, it can\'t be removed.',
							'Error deleting dir' => 'Error deleting dir'
						);
			default:
				return $this->_errors('en');
		}

	}

	public function setConfig(array $config)
	{
		$default = array(
			'ImagesPath'	=> '',
			'FilesPath'		=> '',
			'Path'			=> '',
			'AllowedImages'	=> array('jpeg', 'jpg', 'gif', 'png'),
			'AllowedFiles'	=> array('doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'mdb', 'accdb', 'swf', 'zip', 'rar', 'rtf', 'pdf', 'psd', 'mp3', 'wma'),
			'Width'			=> 500,
			'Height'		=> 500,
			'CLASS_LINK'	=> 'lightview',
			'REL_LINK'		=> 'lightbox',
			'errors'		=> $this->_errors(array_key_exists('Language', $config) ? $config['Language'] : 'en')
		);

		$this->config = array_merge($default, $config);
		$this->errors = &$this->config['errors'];

		$this->ALLOWED_IMAGES = $this->config['AllowedImages'];	//Images dir (root relative)
		$this->ALLOWED_FILES = $this->config['AllowedFiles'];		//Files dir (root relative)
		$this->path = $this->config['Path'];
		$this->dir = array(
			'images' => realpath(FCPATH.$config['ImagesPath']),
			'files' => realpath(FCPATH.$config['FilesPath'])
		);

		//Width and height of resized image
		$this->WIDTH_TO_LINK = $this->config['Width'];
		$this->HEIGHT_TO_LINK = $this->config['Height'];

		//Additional attributes class and rel
		$this->CLASS_LINK = $this->config['CLASS_LINK'];
		$this->REL_LINK = $this->config['REL_LINK'];
	}

	/**
	 * Comprobación de permisos de escritura en la carpeta (no del sistema)
	 *
	 * @param string $requestDirectory Pedir una carpeta (o relativamente DIR_IMAGES DIR_FILES)
	 * @param (images|files) $typeDirectory Tipo de carpeta de imágenes o archivos
	 * @return path|false
	 */
	function AccessDir($requestDirectory, $typeDirectory)
	{
		if( $typeDirectory == 'images' )
		{
			$full_request_images_dir = realpath($this->dir['images'].$requestDirectory);
			if( strpos($full_request_images_dir, $this->dir['images']) === 0 )
			{
				return $full_request_images_dir;
			} else
				return false;
		} elseif( $typeDirectory == 'files' )
		{
			$full_request_files_dir = realpath($this->dir['files'].$requestDirectory);
			if( strpos($full_request_files_dir, $this->dir['files']) === 0 )
			{
				return $full_request_files_dir;
			} else
				return false;
		} else
			return false;
	}

	/**
	 * el árbol de directorios
	 * función recursiva
	 *
	 * @return array
	 */
	function Tree($beginFolder)
	{
		$struct = array();
		$handle = opendir($beginFolder);
		if( $handle )
		{
			$struct[$beginFolder]['path'] = str_replace(array($this->dir['files'], $this->dir['images']), '', $beginFolder);
			$tmp = preg_split('[\\/]', $beginFolder);
			$tmp = array_filter($tmp);
			end($tmp);
			$struct[$beginFolder]['name'] = current($tmp);
			$struct[$beginFolder]['count'] = 0;
			while( false !== ($file = readdir($handle)) )
			{
				if( $file != "." && $file != ".." && $file != '.thumbs' )
				{
					if( is_dir($beginFolder.'/'.$file) )
					{
						$struct[$beginFolder]['childs'][] = $this->Tree($beginFolder.'/'.$file);
					}
					else
					{
						$struct[$beginFolder]['count']++;
					}
				}
			}
			closedir($handle);
			asort($struct);
			return $struct;
		}
		return false;
	}

	/**
	 * Visualización de un árbol de directorios
	 * función recursiva
	 *
	 * @param images|files $type
	 * @param first|String $innerDirs
	 * @param String $currentDir
	 * @param int $level
	 * @return html
	 */
	function DirStructure($type, $innerDirs='first', $currentDir='', $level=0)
	{
		//Mientras que desactipublic la---???
		if( $type == 'files' )
			return;

		$currentDirArr = array();
		if( !empty($currentDir) )
		{
			$currentDirArr = preg_split('[\\/]', str_replace($this->dir[$type], '', realpath($currentDir)));
			$currentDirArr = array_filter($currentDirArr);
		}

		if( $innerDirs == 'first' )
		{
			$innerDirs = array();
			$innerDirs = $this->Tree($this->dir[$type]);
			if( realpath($currentDir) == $this->dir[$type] && !$this->firstAct )
			{
				$firstAct = 'folderAct';
				$this->firstAct = true;
			}
			else
			{
				$firstAct = '';
			}
			$ret = '';
			if( $innerDirs == false )
				return 'Problemas con el directorio raiz ('.DIR_IMAGES.')';
			foreach( $innerDirs as $v )
			{
				$ret = '<div class="folder'.ucfirst($type).' '.$firstAct.'" path="" pathtype="'.$type.'">'.($type == 'images' ? 'Images' : 'Files').($v['count'] > 0 ? ' ('.$v['count'].')' : '').'</div><div class="folderOpenSection" style="display:block;">';
				if( isset($v['childs']) )
				{
					$ret .= $this->DirStructure($type, $v['childs'], $currentDir, $level);
				}
				break;
			}
			$ret .= '</div>';
			return $ret;
		}

		if( sizeof($innerDirs) == 0 )
			return false;
		$ret = '';
		foreach( $innerDirs as $v )
		{
			foreach( $v as $v )
			{

			}
			if( isset($v['count']) )
			{
				$files = 'Files: '.$v['count'];
				$count_childs = isset($v['childs']) ? sizeof($v['childs']) : 0;
				if( $count_childs != 0 )
				{
					$files .= ', directories: '.$count_childs;
				}
			}
			else
			{
				$files = '';
			}
			if( isset($v['childs']) )
			{
				$folderOpen = '';
				$folderAct = '';
				$folderClass = 'folderS';
				if( isset($currentDirArr[$level + 1]) )
				{
					if( $currentDirArr[$level + 1] == $v['name'] )
					{
						$folderOpen = 'style="display:block;"';
						$folderClass = 'folderOpened';
						if( $currentDirArr[sizeof($currentDirArr)] == $v['name'] && !$this->folderAct )
						{
							$folderAct = 'folderAct';
							$this->folderAct = true;
						}
						else
						{
							$folderAct = '';
						}
					}
				}
				$ret .= '<div class="'.$folderClass.' '.$folderAct.'" path="'.$v['path'].'" title="'.$files.'" pathtype="'.$type.'">'.$v['name'].($v['count'] > 0 ? ' ('.$v['count'].')' : '').'</div><div class="folderOpenSection" '.$folderOpen.'>';
				$ret .= $this->DirStructure($type, $v['childs'], $currentDir, $level + 1);
				$ret .= '</div>';
			}
			else
			{
				$soc = sizeof($currentDirArr);
				if( $soc > 0 && $currentDirArr[$soc] == $v['name'] )
				{
					$folderAct = 'folderAct';
				}
				else
				{
					$folderAct = '';
				}
				$ret .= '<div class="folderClosed '.$folderAct.'" path="'.$v['path'].'" title="'.$files.'" pathtype="'.$type.'">'.$v['name'].($v['count'] > 0 ? ' ('.$v['count'].')' : '').'</div>';
			}
		}

		return $ret;
	}

	/**
	 * El camino (bread crumbs)
	 *
	 * @param images|files $type
	 * @param String $path
	 * @return html
	 */
	function DirPath($type, $path='')
	{

		if( !empty($path) )
		{
			$path = preg_split('[\\/]', str_replace($this->dir[$type], '', realpath($path)));
			$path = array_filter($path);
		}

		$ret = '<div class="addrItem" path="" pathtype="'.$type.'" title=""><img src="img/'.($type == 'images' ? 'folder_open_image' : 'folder_open_document').'.png" width="16" height="16" alt="Root dir" /></div>';
		$i = 0;
		$addPath = '';
		if( is_array($path) )
		{
			foreach( $path as $v )
			{
				$i++;
				$addPath .= '/'.$v;
				if( sizeof($path) == $i )
				{
					$ret .= '<div class="addrItemEnd" path="'.$addPath.'" pathtype="'.$type.'" title=""><div>'.$v.'</div></div>';
				}
				else
				{
					$ret .= '<div class="addrItem" path="'.$addPath.'" pathtype="'.$type.'" title=""><div>'.$v.'</div></div>';
				}
			}
		}


		return $ret;
	}

	function CallDir($dir, $type)
	{
		$dir = $this->AccessDir($dir, $type);
		if( !$dir )
			return false;

		set_time_limit(120);

		if( !is_dir($dir.'/.thumbs') )
		{
			mkdir($dir.'/.thumbs');
		}

		$dbfile = $dir.'/.thumbs/.db';
		if( is_file($dbfile) )
		{
			$dbfilehandle = fopen($dbfile, "r");
			$dblength = filesize($dbfile);
			if( $dblength > 0 )
				$dbdata = fread($dbfilehandle, $dblength);
			fclose($dbfilehandle);
			$dbfilehandle = fopen($dbfile, "w");
		} else
		{
			$dbfilehandle = fopen($dbfile, "w");
		}

		if( !empty($dbdata) )
		{
			$files = unserialize($dbdata);
		} else
			$files = array();

		$handle = opendir($dir);
		if( $handle )
		{
			while( false !== ($file = readdir($handle)) )
			{
				if( $file != "." && $file != ".." )
				{
					if( isset($files[$file]) )
						continue;
					if( is_file($dir.'/'.$file) )
					{
						$file_info = pathinfo($dir.'/'.$file);
						$file_info['extension'] = strtolower($file_info['extension']);
						if( !in_array(strtolower($file_info['extension']), $this->ALLOWED_IMAGES) )
						{
							continue;
						}
						$link = str_replace(array('/\\', '//', '\\\\', '\\'), '/',
						  '/'.str_replace(realpath(FCPATH), '', realpath($dir.'/'.$file)));
						$path = pathinfo($link);
						$path = $path['dirname'];
						if( $file_info['extension'] == 'jpg' || $file_info['extension'] == 'jpeg' )
						{
							$er = new phpExifReader($dir.'/'.$file);
							$files[$file]['exifinfo'] = $er->getImageInfo();
							$files[$file]['imageinfo'] = getimagesize($dir.'/'.$file);

							$files[$file]['general'] = array(
								'filename' => $file,
								'name' => basename(strtolower($file_info['basename']), '.'.$file_info['extension']),
								'ext' => $file_info['extension'],
								'path' => $path,
								'link' => $link,
								'size' => filesize($dir.'/'.$file),
								'date' => filemtime($dir.'/'.$file),
								'width' => $files[$file]['imageinfo'][0],
								'height' => $files[$file]['imageinfo'][1],
								'md5' => md5_file($dir.'/'.$file)
							);
						}
						else
						{
							$files[$file]['imageinfo'] = getimagesize($dir.'/'.$file);
							$files[$file]['general'] = array(
								'filename' => $file,
								'name' => basename(strtolower($file_info['basename']), '.'.$file_info['extension']),
								'ext' => $file_info['extension'],
								'path' => $path,
								'link' => $link,
								'size' => filesize($dir.'/'.$file),
								'date' => filemtime($dir.'/'.$file),
								'width' => $files[$file]['imageinfo'][0],
								'height' => $files[$file]['imageinfo'][1],
								'md5' => md5_file($dir.'/'.$file)
							);
						}
					}
				}
			}
			closedir($handle);
		}

		fwrite($dbfilehandle, serialize($files));
		fclose($dbfilehandle);

		return $files;
	}

	function UploadFile($dir, $type)
	{
		$dir = $this->AccessDir($dir, $type);
		if( !$dir )
			return false;

		if( !is_dir($dir.'/.thumbs') )
		{
			mkdir($dir.'/.thumbs');
		}

		$dbfile = $dir.'/.thumbs/.db';
		if( is_file($dbfile) )
		{
			$dbfilehandle = fopen($dbfile, "r");
			$dblength = filesize($dbfile);
			if( $dblength > 0 )
				$dbdata = fread($dbfilehandle, $dblength);
			fclose($dbfilehandle);
			//$dbfilehandle = fopen($dbfile, "w");
		} else
		{
			//$dbfilehandle = fopen($dbfile, "w");
		}

		if( !empty($dbdata) )
		{
			$files = unserialize($dbdata);
		} else
			$files = array();

		//File from the flash-Multiboot
		if( isset($_POST['Filename']) )
		{
			//Type (image / file)
			$pathtype = $_POST['pathtype'];
			if( strpos($_POST['Filename'], '.') !== false )
			{
				$extension = end(explode('.', $_POST['Filename']));
				$filename = substr($_POST['Filename'], 0, strlen($_POST['Filename']) - strlen($extension) - 1);
			}
			else
			{
				header('HTTP/1.1 403 Forbidden');
				return false;
			}
			if( $pathtype == 'images' )
				$allowed = $this->ALLOWED_IMAGES;
			elseif( $pathtype == 'files' )
				$allowed = $this->ALLOWED_FILES;
			//If the file is not suitable resolution
			if( !in_array(strtolower($extension), $allowed) )
			{
				header('HTTP/1.1 403 Forbidden');
				return false;
			}

			$md5 = md5_file($_FILES['Filedata']['tmp_name']);
			$file = $md5.'.'.$extension;

			//Check for image
			if( $pathtype == 'images' )
			{
				$files[$file]['imageinfo'] = getimagesize($_FILES['Filedata']['tmp_name']);

				if( empty($files[$file]['imageinfo']) )
				{
					header('HTTP/1.1 403 Forbidden');
					return false;
				}
			}

			if( !copy($_FILES['Filedata']['tmp_name'], $dir.'/'.$file) )
			{
				header('HTTP/1.0 500 Internal Server Error');
				return false;
			}

			$link = str_replace(array('/\\', '//', '\\\\', '\\'), '/',
					   '/'.str_replace(realpath(FCPATH), '', realpath($dir.'/'.$file)));
			$path = pathinfo($link);
			$path = $path['dirname'];
			if( $extension == 'jpg' || $extension == 'jpeg' )
			{
				$er = new phpExifReader($dir.'/'.$file);
				$files[$file]['exifinfo'] = $er->getImageInfo();

				$files[$file]['general'] = array(
					'filename' => $file,
					'name' => $filename,
					'ext' => $extension,
					'path' => $path,
					'link' => $link,
					'size' => filesize($dir.'/'.$file),
					'date' => filemtime($dir.'/'.$file),
					'width' => $files[$file]['imageinfo'][0],
					'height' => $files[$file]['imageinfo'][1],
					'md5' => $md5
				);
			}
			else
			{
				$files[$file]['general'] = array(
					'filename' => $file,
					'name' => $filename,
					'ext' => $extension,
					'path' => $path,
					'link' => $link,
					'size' => filesize($dir.'/'.$file),
					'date' => filemtime($dir.'/'.$file),
					'width' => $files[$file]['imageinfo'][0],
					'height' => $files[$file]['imageinfo'][1],
					'md5' => $md5
				);
			}
		}
		//Files from the normal load
		else
		{
			sort($_FILES);
			$ufiles = $_FILES[0];

			foreach( $ufiles['name'] as $k => $v )
			{
				if( $ufiles['error'][$k] != 0 )
					continue;

				//Type (image / file)
				$pathtype = $_POST['pathtype'];
				if( strpos($ufiles['name'][$k], '.') !== false )
				{
					$extension = end(explode('.', $ufiles['name'][$k]));
					$filename = substr($ufiles['name'][$k], 0, strlen($ufiles['name'][$k]) - strlen($extension) - 1);
				}
				else
				{
					continue;
				}
				if( $pathtype == 'images' )
					$allowed = $this->ALLOWED_IMAGES;
				elseif( $pathtype == 'files' )
					$allowed = $this->ALLOWED_FILES;
				//If no suitable file extension
				if( !in_array(strtolower($extension), $allowed) )
				{
					continue;
				}

				$md5 = md5_file($ufiles['tmp_name'][$k]);
				$file = $md5.'.'.$extension;

				//Check for image
				if( $pathtype == 'images' )
				{
					$files[$file]['imageinfo'] = getimagesize($ufiles['tmp_name'][$k]);

					if( empty($files[$file]['imageinfo']) )
					{
						header('HTTP/1.1 403 Forbidden');
						return false;
					}
				}

				if( !copy($ufiles['tmp_name'][$k], $dir.'/'.$file) )
				{
					continue;
				}
				$link = str_replace(array('/\\', '//', '\\\\', '\\'), '/',
						'/'.str_replace(realpath(FCPATH), '', realpath($dir.'/'.$file)));
				$path = pathinfo($link);
				$path = $path['dirname'];
				if( $extension == 'jpg' || $extension == 'jpeg' )
				{
					$er = new phpExifReader($dir.'/'.$file);
					$files[$file]['exifinfo'] = $er->getImageInfo();

					$files[$file]['general'] = array(
						'filename' => $file,
						'name' => $filename,
						'ext' => $extension,
						'path' => $path,
						'link' => $link,
						'size' => filesize($dir.'/'.$file),
						'date' => filemtime($dir.'/'.$file),
						'width' => $files[$file]['imageinfo'][0],
						'height' => $files[$file]['imageinfo'][1],
						'md5' => $md5
					);
				}
				else
				{
					$files[$file]['general'] = array(
						'filename' => $file,
						'name' => $filename,
						'ext' => $extension,
						'path' => $path,
						'link' => $link,
						'size' => filesize($dir.'/'.$file),
						'date' => filemtime($dir.'/'.$file),
						'width' => $files[$file]['imageinfo'][0],
						'height' => $files[$file]['imageinfo'][1],
						'md5' => $md5
					);
				}
			}
		}

		$dbfilehandle = fopen($dbfile, "w");
		fwrite($dbfilehandle, serialize($files));
		fclose($dbfilehandle);

		return '';
	}

	function RenameFile($type, $dir, $filename, $newname)
	{
		$dir = $this->AccessDir($dir, $type);
		if( !$dir )
			return false;

		$filename = trim($filename);

		if( empty($filename) )
		{
			return 'error';
		}

		if( !is_dir($dir.'/.thumbs') )
		{
			return 'error';
		}

		$dbfile = $dir.'/.thumbs/.db';
		if( is_file($dbfile) )
		{
			$dbfilehandle = fopen($dbfile, "r");
			$dblength = filesize($dbfile);
			if( $dblength > 0 )
				$dbdata = fread($dbfilehandle, $dblength);
			fclose($dbfilehandle);
		} else
		{
			return 'error';
		}

		$files = unserialize($dbdata);

		foreach( $files as $file => $fdata )
		{
			if( $file == $filename )
			{
				$files[$file]['general']['name'] = $newname;
				break;
			}
		}

		$dbfilehandle = fopen($dbfile, "w");
		fwrite($dbfilehandle, serialize($files));
		fclose($dbfilehandle);

		return 'ok';
	}

	function ShowDir($dir, $type)
	{
		$dir = $this->CallDir($dir, $type);

		if( !$dir )
		{
			//echo 'Error al leer, puede no tenerse acceso.';
			return '';
		}
		$ret = '';
		foreach( $dir as $v )
		{
			$thumb = $this->GetThumb($v['general']['path'], $v['general']['md5'], $v['general']['filename'], 2, 100, 100);
			if( $v['general']['width'] > $this->WIDTH_TO_LINK || $v['general']['height'] > $this->HEIGHT_TO_LINK )
			{
				if( $v['general']['width'] > $v['general']['height'] )
				{
					$middle_thumb = $this->GetThumb($v['general']['path'], $v['general']['md5'], $v['general']['filename'], 0,
									 $this->WIDTH_TO_LINK, 0);
				}
				else
				{
					$middle_thumb = $this->GetThumb($v['general']['path'], $v['general']['md5'], $v['general']['filename'], 0, 0,
									 $this->HEIGHT_TO_LINK);
				}
				list($middle_width, $middle_height) = getimagesize(FCPATH.$middle_thumb);
				$middle_thumb_attr = 'fmiddle="'.$middle_thumb.'" fmiddlewidth="'.$middle_width.'" fmiddleheight="'.$middle_height.'" fclass="'.$this->CLASS_LINK.'" frel="'.$this->REL_LINK.'"';
			}
			else
			{
				$middle_thumb = '';
				$middle_thumb_attr = '';
			}
			$ret .= '
   <table class="imageBlock0" cellpadding="0" cellspacing="0" filename="'.$v['general']['filename'].'" fname="'.$v['general']['name'].'" ext="'.strtoupper($v['general']['ext']).'" path="'.$v['general']['path'].'" linkto="'.$v['general']['link'].'" fsize="'.$v['general']['size'].'" date="'.date('d.m.Y H:i',$v['general']['date']).'" fwidth="'.$v['general']['width'].'" fheight="'.$v['general']['height'].'" md5="'.$v['general']['md5'].'" '.$middle_thumb_attr.'><tr><td valign="bottom" align="center">
    <div class="imageBlock1">
     <div class="imageImage">
      <img src="'.$thumb.'" width="100" height="100" alt="'.$v['general']['name'].'" />
     </div>
     <div class="imageName">'.$v['general']['name'].'</div>
    </div>
   </td></tr></table>
			';
		}

		return $ret;
	}

	function GetThumb($dir, $md5, $filename, $mode, $width=100, $height=100)
	{
		$path = realpath(FCPATH.'/'.$dir);
		if( is_file($path.'/.thumbs/'.$md5.'_'.$width.'_'.$height.'_'.$mode.'.jpg') )
			return $dir.'/.thumbs/'.$md5.'_'.$width.'_'.$height.'_'.$mode.'.jpg';

		$t = new Image_Toolbox($path.'/'.$filename);
		$t->newOutputSize($width, $height, $mode, false, '#FFFFFF');
		$t->save($path.'/.thumbs/'.$md5.'_'.$width.'_'.$height.'_'.$mode.'.jpg', 'jpg', 80);
		return $dir.'/.thumbs/'.$md5.'_'.$width.'_'.$height.'_'.$mode.'.jpg';
	}

	function DelFile($pathtype, $path, $md5, $filename, $callShowDir=false)
	{
		$tmppath = $path;
		$path = $this->AccessDir($path, $pathtype);
		if( !$path )
			return false;

		if( is_dir($path.'/.thumbs') )
		{
			if( $pathtype == 'images' )
			{
				$handle = opendir($path.'/.thumbs');
				if( $handle )
				{
					while( false !== ($file = readdir($handle)) )
					{
						if( $file != "." && $file != ".." )
						{
							if( substr($file, 0, 32) == $md5 )
							{
								unlink($path.'/.thumbs/'.$file);
							}
						}
					}
				}
			}

			$dbfile = $path.'/.thumbs/.db';
			if( is_file($dbfile) )
			{
				$dbfilehandle = fopen($dbfile, "r");
				$dblength = filesize($dbfile);
				if( $dblength > 0 )
					$dbdata = fread($dbfilehandle, $dblength);
				fclose($dbfilehandle);
				$dbfilehandle = fopen($dbfile, "w");
			} else
			{
				$dbfilehandle = fopen($dbfile, "w");
			}


			if( isset($dbdata) )
			{
				$files = unserialize($dbdata);
			} else
				$files = array();

			unset($files[$filename]);

			fwrite($dbfilehandle, serialize($files));
			fclose($dbfilehandle);
		}

		if( is_file($path.'/'.$filename) )
		{
			if( unlink($path.'/'.$filename) )
			{
				if( $callShowDir )
				{
					return $this->ShowDir($tmppath, $pathtype);
				}
				else
				{
					return true;
				}
			}
		} else
			return 'error';

		return 'error';
	}

	function DelFolder($pathtype, $path)
	{
		$path = $this->AccessDir($path, $pathtype);
		if( !$path )
			return false;

		if( realpath($path.'/') == realpath(FCPATH.DIR_IMAGES.'/') )
		{
			$return = array('error' => $this->errors['You can not delete a root dir!']);
			return json_encode($return);
		}

		$files = array();

		$handle = opendir($path);
		if( $handle )
		{
			while( false !== ($file = readdir($handle)) )
			{
				if( $file != "." && $file != ".." && trim($file) != "" && $file != ".thumbs" )
				{
					if( is_dir($path.'/'.$file) )
					{
						$return = array('error' => $this->errors['As long the dir contains subfolders, it can\'t be removed.']);
						return json_encode($return);
					}
					else
					{
						$files[] = $file;
					}
				}
			}
		}
		closedir($handle);

		$handle = opendir($path.'/.thumbs');
		if( $handle )
		{
			while( false !== ($file = readdir($handle)) )
			{
				if( $file != "." && $file != ".." )
				{
					if( is_file($path.'/.thumbs/'.$file) )
					{
						unlink($path.'/.thumbs/'.$file);
					}
				}
			}
			closedir($handle);
			rmdir($path.'/.thumbs');
		}

		foreach( $files as $f )
		{
			if( is_file($path.'/'.$f) )
				unlink($path.'/'.$f);
		}

		if( !rmdir($path) )
		{
			$return = array('error' => $this->errors['Error deleting dir']);
			return json_encode($return);
		}
		return '{ok:\'\'}';
	}

}
