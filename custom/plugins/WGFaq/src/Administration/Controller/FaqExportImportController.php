<?php

declare(strict_types=1);

namespace WG\Faq\Administration\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class FaqExportImportController extends AbstractController
{
    public function __construct(
        private readonly EntityRepository $faqRepository,
        private readonly EntityRepository $categoryRepository
    ) {
    }

    #[Route(
        path: '/api/wg-faq/export',
        name: 'api.wg_faq.export',
        methods: ['GET']
    )]
    public function export(Context $context): Response
    {
        try {
            return $this->doExport($context);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Export fehlgeschlagen: ' . $e->getMessage(),
                'trace' => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    }

    private function doExport(Context $context): Response
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addAssociation('translations');

        $faqs = $this->faqRepository->search($criteria, $context);

        // Resolve category names for readability
        $allCategoryIds = [];
        foreach ($faqs->getEntities() as $faq) {
            $catIds = $faq->getCategoryIds();
            if (\is_array($catIds)) {
                $allCategoryIds = array_merge($allCategoryIds, $catIds);
            }
        }
        $allCategoryIds = array_unique(array_filter($allCategoryIds));

        $categoryNames = [];
        if (!empty($allCategoryIds)) {
            try {
                $catCriteria = new Criteria(array_values($allCategoryIds));
                $catCriteria->addAssociation('translations');
                $categories = $this->categoryRepository->search($catCriteria, $context);
                foreach ($categories->getEntities() as $cat) {
                    $name = $cat->getTranslation('name');
                    if ($name === null) {
                        $name = $cat->getName();
                    }
                    $categoryNames[$cat->getId()] = $name ?? '(unbekannt)';
                }
            } catch (\Throwable $e) {
                // Category resolution failed - continue without names
            }
        }

        $exportData = [];
        foreach ($faqs->getEntities() as $faq) {
            $translations = [];
            /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection|null $faqTranslations */
            $faqTranslations = $faq->get('translations');
            if ($faqTranslations !== null) {
                foreach ($faqTranslations as $translation) {
                    $translations[] = [
                        'languageId' => $translation->get('languageId'),
                        'question' => $translation->get('question'),
                        'answer' => $translation->get('answer'),
                        'seoUrl' => $translation->get('seoUrl'),
                        'metaTitle' => $translation->get('metaTitle'),
                        'metaDescription' => $translation->get('metaDescription'),
                    ];
                }
            }

            // Build category info with names for readability
            $categoryInfo = [];
            $catIds = $faq->getCategoryIds();
            if (\is_array($catIds)) {
                foreach ($catIds as $catId) {
                    $categoryInfo[] = [
                        'id' => $catId,
                        'name' => $categoryNames[$catId] ?? '(unbekannt)',
                    ];
                }
            }

            $exportData[] = [
                'id' => $faq->getId(),
                'active' => $faq->isActive(),
                'position' => $faq->getPosition(),
                'showOnHome' => $faq->isShowOnHome(),
                'showOnCategory' => $faq->isShowOnCategory(),
                'showOnProduct' => $faq->isShowOnProduct(),
                'showOnFaqPage' => $faq->isShowOnFaqPage(),
                'categories' => $categoryInfo,
                'videoUrl' => $faq->getVideoUrl(),
                'videoType' => $faq->getVideoType(),
                'translations' => $translations,
            ];
        }

        $json = json_encode([
            'version' => '1.0',
            'exportedAt' => (new \DateTimeImmutable())->format('c'),
            'totalCount' => count($exportData),
            'faqs' => $exportData,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new Response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="wg-faq-export-' . date('Y-m-d') . '.json"',
        ]);
    }

    #[Route(
        path: '/api/wg-faq/import',
        name: 'api.wg_faq.import',
        methods: ['POST']
    )]
    public function import(Request $request, Context $context): JsonResponse
    {
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (!$data || !isset($data['faqs']) || !is_array($data['faqs'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ungültiges JSON-Format. Erwartet: {"faqs": [...]}',
            ], 400);
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($data['faqs'] as $index => $faqData) {
            try {
                $upsertData = $this->buildUpsertData($faqData);

                // Check if FAQ with this ID already exists
                if (isset($faqData['id'])) {
                    $existing = $this->faqRepository->search(
                        new Criteria([$faqData['id']]),
                        $context
                    )->first();

                    if ($existing) {
                        $upsertData['id'] = $faqData['id'];
                        $this->faqRepository->update([$upsertData], $context);
                        $updated++;
                        continue;
                    }
                }

                // Create new FAQ
                $upsertData['id'] = $faqData['id'] ?? Uuid::randomHex();
                $this->faqRepository->create([$upsertData], $context);
                $created++;
            } catch (\Throwable $e) {
                $question = $faqData['translations'][0]['question'] ?? "FAQ #$index";
                $errors[] = "Fehler bei \"$question\": " . $e->getMessage();
            }
        }

        return new JsonResponse([
            'success' => empty($errors),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'message' => sprintf(
                '%d erstellt, %d aktualisiert%s',
                $created,
                $updated,
                !empty($errors) ? ', ' . count($errors) . ' Fehler' : ''
            ),
        ]);
    }

    private function buildUpsertData(array $faqData): array
    {
        $result = [
            'active' => $faqData['active'] ?? true,
            'position' => $faqData['position'] ?? 0,
            'showOnHome' => $faqData['showOnHome'] ?? false,
            'showOnCategory' => $faqData['showOnCategory'] ?? false,
            'showOnProduct' => $faqData['showOnProduct'] ?? false,
            'showOnFaqPage' => $faqData['showOnFaqPage'] ?? true,
            'videoUrl' => $faqData['videoUrl'] ?? null,
            'videoType' => $faqData['videoType'] ?? null,
        ];

        // Handle categories - accept both formats
        if (isset($faqData['categories']) && is_array($faqData['categories'])) {
            $categoryIds = [];
            foreach ($faqData['categories'] as $cat) {
                if (is_array($cat) && isset($cat['id'])) {
                    $categoryIds[] = $cat['id'];
                } elseif (is_string($cat)) {
                    $categoryIds[] = $cat;
                }
            }
            $result['categoryIds'] = !empty($categoryIds) ? $categoryIds : null;
        } elseif (isset($faqData['categoryIds'])) {
            $result['categoryIds'] = $faqData['categoryIds'];
        }

        // Handle translations
        if (isset($faqData['translations']) && is_array($faqData['translations'])) {
            $result['translations'] = [];
            foreach ($faqData['translations'] as $translation) {
                if (!isset($translation['languageId'])) {
                    continue;
                }
                $result['translations'][$translation['languageId']] = [
                    'question' => $translation['question'] ?? '',
                    'answer' => $translation['answer'] ?? '',
                    'seoUrl' => $translation['seoUrl'] ?? null,
                    'metaTitle' => $translation['metaTitle'] ?? null,
                    'metaDescription' => $translation['metaDescription'] ?? null,
                ];
            }
        }

        return $result;
    }
}
