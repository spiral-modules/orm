<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations\Traits;

/**
 * Provides ability to skip loading data from database for realtion.
 */
trait PartialTrait
{
    /**
     * @var bool
     */
    protected $autoload = true;

    /**
     * Partial selections will not be autoloaded.
     *
     * Example:
     *
     * $post = $this->findPost(); //no comments
     * $post->comments->partial(true);
     * $post->comments->add(new Comment());
     * assert($post->comments->count() == 1); //no other comments to be loaded
     *
     * $post->comments->add($comment);
     *
     * @param bool $partial
     *
     * @return $this
     */
    public function partial(bool $partial = true)
    {
        $this->autoload = !$partial;

        return $this;
    }

    /**
     * @return bool
     */
    public function isPartial(): bool
    {
        return !$this->autoload;
    }
}