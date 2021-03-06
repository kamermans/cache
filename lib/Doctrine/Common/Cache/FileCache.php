<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\Common\Cache;

/**
 * Base file cache driver.
 *
 * @since  2.3
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
abstract class FileCache extends CacheProvider
{
    /**
     * The cache directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * The cache file extension.
     *
     * @var string|null
     */
    protected $extension;

    /**
     * The mode that directories will be created with.
     *
     * @var int
     */
    protected $directoryMode = 0777;
    
    /**
     * Cached objects are stored in directories.  These directory names are determined
     * by splitting up the 32-char ID of the object into x parts.  If x=16 files will
     * be two dirs deep (32/16=2).  If x=2 files will be eight dirs deep (32/2=16)
     * 
     * @var int
     */
    protected $directorySpreadChars = 16;

    /**
     * The mode that files will be created with.  Null means the file will be created
     * with the current umask.
     *
     * @var int|null
     */
    protected $fileMode;

    /**
     * The hash algorithm that is used to generate filenames
     * 
     * @var string
     */
    protected $hasher = 'sha256';

    /**
     * Constructor.
     *
     * @param string      $directory The cache directory.
     * @param string|null $extension The cache file extension.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($directory, $extension = null)
    {
        if ( ! is_dir($directory) && ! @mkdir($directory, $this->directoryMode, true)) {
            throw new \InvalidArgumentException(sprintf(
                'The directory "%s" does not exist and could not be created.',
                $directory
            ));
        }

        if ( ! is_writable($directory)) {
            throw new \InvalidArgumentException(sprintf(
                'The directory "%s" is not writable.',
                $directory
            ));
        }

        $this->directory = realpath($directory);
        $this->extension = $extension ?: $this->extension;
        $this->fileMode = 0666 & ~umask();
    }

    /**
     * Sets the mode that new directories will be created with.
     * @param int $mode Mode, normally in octal (ex: 0755)
     */
    public function setDirectoryMode($mode)
    {
        if ( ! is_int($mode)) {
            throw new \InvalidArgumentException("You must specify permissions modes as an int");
        }
        $this->directoryMode = $mode;
    }

    /**
     * Sets the mode that new files will be created with.
     * @param int $mode Mode, normally in octal (ex: 0644)
     */
    public function setFileMode($mode)
    {
        if ( ! is_int($mode)) {
            throw new \InvalidArgumentException("You must specify permissions modes as an int");
        }
        $this->fileMode = $mode;
    }

    /**
     * Sets the hashing algorithm that is used to generate filenames.
     * 
     * @param string $haserh
     */
    public function setHasher($hasher)
    {
        $this->hasher = $hasher;
    }

    /**
     * Cached objects are stored in directories.  These directory names are determined
     * by splitting up the 32-char ID of the object into x parts.  
     *   If x=16 files will be two dirs deep (32/16=2 ex: 1234567890123456/1234567890123456)
     *   If x=2 files will be eight dirs deep (32/2=16 ex: 12/34/56/78/90/12/34/56/12/34/56/78/90/12/34/56)
     * 
     * @var int
     */
    public function setDirectorySpreadChars($chars)
    {
        if ( ! is_int($chars)) {
            throw new \InvalidArgumentException("You must specify directory spread chars as an int");
        }
        $this->directorySpreadChars = $chars;
    }

    /**
     * Gets the cache directory.
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Gets the cache file extension.
     *
     * @return string|null
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @param string $id
     *
     * @return string
     */
    protected function getFilename($id)
    {
        $hash = hash($this->hasher, $id);
        $path = implode(str_split($hash, $this->directorySpreadChars), DIRECTORY_SEPARATOR);
        $path = $this->directory . DIRECTORY_SEPARATOR . $path;
        $id   = preg_replace('@[\\\/:"*?<>|]+@', '', $id);

        return $path . DIRECTORY_SEPARATOR . $id . $this->extension;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        return @unlink($this->getFilename($id));
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        foreach ($this->getIterator() as $name => $file) {
            @unlink($name);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
        $usage = 0;
        foreach ($this->getIterator() as $file) {
            $usage += $file->getSize();
        }

        $free = disk_free_space($this->directory);

        return array(
            Cache::STATS_HITS               => null,
            Cache::STATS_MISSES             => null,
            Cache::STATS_UPTIME             => null,
            Cache::STATS_MEMORY_USAGE       => $usage,
            Cache::STATS_MEMORY_AVAILABLE   => $free,
        );
    }

    /**
     * @return \Iterator
     */
    private function getIterator()
    {
        $pattern = '/^.+\\' . $this->extension . '$/i';
        $iterator = new \RecursiveDirectoryIterator($this->directory);
        $iterator = new \RecursiveIteratorIterator($iterator);
        return new \RegexIterator($iterator, $pattern);
    }
}
