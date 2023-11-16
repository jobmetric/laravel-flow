<?php

namespace JobMetric\Flow\Commands;

trait ConsoleTools
{
    /**
     * get stub file
     *
     * @param string $path
     * @param array $items
     * @return string
     */
    protected function getStub(string $path, array $items = []): string
    {
        $content = file_get_contents(__DIR__ . '/stub/' . $path . '.php.stub');

        foreach ($items as $key => $item) {
            $content = str_replace('{{' . $key . '}}', $item, $content);
        }

        return $content;
    }

    /**
     * save file
     *
     * @param string $path
     * @param string $content
     *
     * @return void
     */
    protected function putFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }

    /**
     * check if directory exists
     *
     * @param string $path
     *
     * @return bool
     */
    protected function isDir(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * make directory
     *
     * @param string $path
     *
     * @return void
     */
    protected function makeDir(string $path): void
    {
        mkdir($path, 0775, true);
    }

    /**
     * check if file exists
     *
     * @param string $path
     *
     * @return bool
     */
    protected function isFile(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * custom alert for console
     *
     * @param string $message
     * @param string $type
     *
     * @return void
     */
    public function message(string $message, string $type = 'info'): void
    {
        $this->newLine();
        switch ($type) {
            case 'info':
                $this->line("  <bg=blue;options=bold> INFO </> " . $message);
                break;
            case 'error':
                $this->line("  <bg=red;options=bold> ERROR </> " . $message);
                break;
            case'success':
                $this->line("  <bg=green;options=bold> SUCCESS </> " . $message);
        }
        $this->newLine();
    }
}
