<?php

declare(strict_types=1);

namespace WG\Faq\Core\Content\Faq;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class FaqEntity extends Entity
{
    use EntityIdTrait;

    protected bool $active = true;

    protected int $position = 0;

    protected bool $showOnHome = false;

    protected bool $showOnCategory = false;

    protected bool $showOnProduct = false;

    protected bool $showOnFaqPage = true;

    protected ?array $categoryIds = null;

    protected ?string $mediaId = null;

    protected ?string $videoUrl = null;

    protected ?string $videoType = null;

    protected ?string $question = null;

    protected ?string $answer = null;

    protected ?string $seoUrl = null;

    protected ?string $metaTitle = null;

    protected ?string $metaDescription = null;

    protected ?MediaEntity $media = null;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isShowOnHome(): bool
    {
        return $this->showOnHome;
    }

    public function setShowOnHome(bool $showOnHome): void
    {
        $this->showOnHome = $showOnHome;
    }

    public function isShowOnCategory(): bool
    {
        return $this->showOnCategory;
    }

    public function setShowOnCategory(bool $showOnCategory): void
    {
        $this->showOnCategory = $showOnCategory;
    }

    public function isShowOnProduct(): bool
    {
        return $this->showOnProduct;
    }

    public function setShowOnProduct(bool $showOnProduct): void
    {
        $this->showOnProduct = $showOnProduct;
    }

    public function isShowOnFaqPage(): bool
    {
        return $this->showOnFaqPage;
    }

    public function setShowOnFaqPage(bool $showOnFaqPage): void
    {
        $this->showOnFaqPage = $showOnFaqPage;
    }

    public function getCategoryIds(): ?array
    {
        return $this->categoryIds;
    }

    public function setCategoryIds(?array $categoryIds): void
    {
        $this->categoryIds = $categoryIds;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(?string $videoUrl): void
    {
        $this->videoUrl = $videoUrl;
    }

    public function getVideoType(): ?string
    {
        return $this->videoType;
    }

    public function setVideoType(?string $videoType): void
    {
        $this->videoType = $videoType;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(?string $question): void
    {
        $this->question = $question;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $answer): void
    {
        $this->answer = $answer;
    }

    public function getSeoUrl(): ?string
    {
        return $this->seoUrl;
    }

    public function setSeoUrl(?string $seoUrl): void
    {
        $this->seoUrl = $seoUrl;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }
}
