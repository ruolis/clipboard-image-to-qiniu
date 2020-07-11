<?php

require './vendor/autoload.php';

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

class App
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function run(string $type = '') : void
    {
        $name = $this->generate($type);

        $qiniu = $this->upload($name);

        $markdown = $this->toMarkdown($name, $qiniu);

        $this->clear($name);

        echo $markdown;
    }

    protected function makeLocalPath(string $name) : string
    {
        $path = sprintf('%s/%s', rtrim($this->config['tmp_dir'], '/'), $name);

        return $path;
    }

    protected function makeQiniuPath(string $name) : string
    {
        $path = sprintf('%s/%s', date('Y/md'), $name);

        return $path;
    }

    protected function generate(string $type) : string
    {
        $pngpaste = '/usr/local/bin/pngpaste';

        $pngquant = '/usr/local/bin/pngquant';

        $type = $type ?: 'png';

        $name = time() . '.' . $type;

        $path = $this->makeLocalPath($name);

        exec($pngpaste . ' ' . $path);

        if ($type === 'png') {
            exec($pngquant . ' -f -o ' . $path . ' ' . $path);
        }

        return $name;
    }

    protected function upload(string $name) : string
    {
        $auth = new Auth($this->config['access'], $this->config['secret']);

        $token = $auth->uploadToken($this->config['bucket']);

        $local = $this->makeLocalPath($name);
        $qiniu = $this->makeQiniuPath($name);

        [$result, $error] = (new UploadManager)->putFile($token, $qiniu, $local);
        if (!empty($error)) {
            return $error->message();
        }

        $url = $this->makeQiniuURL($result['key']);

        return $url;
    }

    protected function makeQiniuURL(string $path) : string
    {
        $url = rtrim($this->config['domain'], '/') . '/' . $path;

        return $url;
    }

    protected function clear(string $name) : void
    {
        $path = $this->makeLocalPath($name);

        @unlink($path);
    }

    protected function toMarkdown(string $name, string $url) : string
    {
        $markdown = sprintf('![%s](%s)', $name, $url);

        return $markdown;
    }
}
