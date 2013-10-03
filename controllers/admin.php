<?php

class Admin extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
	}

	public function mediaUpload()
	{
		// Add security checks

		$config = array(
			'ImagesPath' => '/images/uploads',
			'FilesPath' => '/images/uploads',
			'Path' => '/images/uploads'
		);
		$this->load->library('TinyImageManager', $config);
	}

}
