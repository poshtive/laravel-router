<?php

declare(strict_types=1);

namespace Poshtive\Router\Support;

use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Where a controller file sits in its discovery directory: its folders and its
 * name, as the kebab-case segments shared by its URIs and route names.
 *
 * Names are used as written unless the group opts into pluralization, which
 * uses Laravel's pluralizer (and therefore Pluralizer::useLanguage()).
 *
 * @internal
 */
final readonly class ControllerPath
{
    /**
     * @param  string  $relative  Path of the file inside the directory, with "/" separators.
     * @param  string  $directory  The discovery directory, ending in a separator.
     * @param  list<string>  $names  The folder names, then the controller name without "Controller".
     */
    private function __construct(
        public string $relative,
        private string $directory,
        private array $names,
        private bool $pluralize,
    ) {}

    public static function of(SplFileInfo $file, bool $pluralize): self
    {
        $relative = Path::normalize($file->getRelativePathname());
        $directory = substr($file->getPathname(), 0, -strlen($file->getRelativePathname()));
        $names = explode('/', (string) preg_replace('/(Controller)?\.php$/', '', $relative));

        return new self($relative, $directory, $names, $pluralize);
    }

    /** @return list<string> */
    public function segments(): array
    {
        return array_map(fn (string $name): string => Str::kebab(
            $this->pluralize && strtolower($name) !== 'index' ? Str::pluralStudly(Str::studly($name)) : Str::studly($name),
        ), $this->names);
    }

    /**
     * The segments with a numbered placeholder after every folder that has a
     * sibling controller (User/ next to UserController.php gives user/{1}).
     *
     * @return list<string>
     */
    public function uriSegments(): array
    {
        $uri = [];
        $folder = $this->directory;
        $placeholder = 1;
        $last = count($this->names) - 1;

        foreach ($this->segments() as $i => $segment) {
            $uri[] = $segment;

            if ($i < $last) {
                if (is_file($folder.$this->names[$i].'Controller.php')) {
                    $uri[] = sprintf('{%d}', $placeholder++);
                }
                $folder .= $this->names[$i].'/';
            }
        }

        return $uri;
    }

    public function hasIndexFolder(): bool
    {
        foreach (array_slice($this->names, 0, -1) as $folder) {
            if (strtolower($folder) === 'index') {
                return true;
            }
        }

        return false;
    }
}
