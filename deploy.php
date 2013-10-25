<?php
/**
 * 这是一个自动化部署的类, 非常简单，思想就是压缩，上传，然后解压覆盖，所以请小心使用.
 * @author liaojh <zhubian1959@gmail.com>
 * @date 2013-10-24
 */

/**
 * 配置
 */
$SECRET_KEY = 'whatthefuck';
$config = array(
    'upload_url' => 'http://dev.youdeploysite.com/deploy.php?key=' . $SECRET_KEY,
    'folders' => array(
        'application/controllers/',
        'application/libraries/',
        'application/models/'
    ),
);

// 开始干活
if ($_FILES) {
    if (!isset($_GET['key']) || $_GET['key'] != $SECRET_KEY) {
        die('invalid request.');
    }
    $deploy = new DeployServer();
    $deploy->deploy($_FILES);
} else if (isset($_SERVER["argv"][1]) && $_SERVER["argv"][1] == 'start'){
    $deploy = new DeployClient($config);
    $deploy->deploy();
} else {
    die('you know. I am here...');
}

/**
 * 部署客户端类
 */
class DeployClient {

    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }

    public function deploy() {
        $tmp_dir = __DIR__ . '/' . md5(time()) . '.zip';
        // 生成压缩文件
        $zip = new ZipHelper();
        $zip->zip($this->config['folders'], $tmp_dir);
        echo $this->_post($this->config['upload_url'], $tmp_dir);

        unlink($tmp_dir);
    }

    private function _post($url, $file) {
        $file = array("file" => "@" . $file); //文件路径，前面要加@，表明是文件上传.
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $file);
        return curl_exec($curl);
    }

}

/**
 * 部署服务器类
 */
class DeployServer {

    public function deploy($files) {
        // step 1: 保存文件
        $tmp_dir = "uploads/" . $_FILES["file"]["name"];
        move_uploaded_file($files["file"]["tmp_name"], $tmp_dir);

        // step 2: 解压并覆盖文件
        $zip = new ZipHelper();
        $zip->unzip($tmp_dir, __DIR__ . '/');

        // step 3: 删除临时文件
        unlink($tmp_dir);

        return true;
    }

}

/**
 * 压缩文件，解压文件类
 */
class ZipHelper {

    private $_zip;

    public function __construct() {
        $this->_zip = new ZipArchive();
    }

    function unzip($unzip, $dst) {
        $res = $this->_zip->open($unzip);
        if ($res === true) {
            if (!$this->_zip->extractTo($dst)) {
                echo 'extract fail to ' . $dst;
            }
            $this->_zip->close();
        } else {
            echo 'failed, code:' . $res;
        }
    }

    function zip($dirs, $dst) {
        $res = $this->_zip->open($dst, ZipArchive::CREATE);
        if ($res === true) {
            if (is_array($dirs)) {
                foreach ($dirs as $dir) {
                    $this->_zip($dir, $this->_zip);
                }
            } else {
                $this->_zip($dirs, $this->_zip);
            }
            $this->_zip->close();
        } else {
            echo 'failed';
        }
    }

    private function _zip($dir, &$zip) {
        if (!is_dir($dir)) {
            return;
        }
        $dh = opendir($dir);
        while (($file = readdir($dh)) !== false) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (filetype($dir . $file) == 'dir') {
                $this->_zip($dir . $file . '/', $zip);
            } else {
                $zip->addFile($dir . $file);
            }
        }
    }

}
