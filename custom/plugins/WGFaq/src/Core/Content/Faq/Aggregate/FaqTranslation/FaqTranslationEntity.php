<?php

declare(strict_types=1);

namespace WG\Faq\Core\Content\Faq\Aggregate\FaqTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class FaqTranslationEntity extends TranslationEntity
{
    protected ?string $question = null;

    protected ?string $answer = null;

    protected ?string $seoUrl = null;

    protected ?string $metaTitle = null;

    protected ?string $metaDescription = null;

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
}
