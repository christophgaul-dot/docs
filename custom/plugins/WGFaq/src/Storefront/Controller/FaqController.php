<?php

declare(strict_types=1);

namespace WG\Faq\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class FaqController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $faqRepository,
        private readonly GenericPageLoader $genericPageLoader
    ) {
    }

    #[Route(
        path: '/faq',
        name: 'frontend.wg.faq.index',
        methods: ['GET']
    )]
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->genericPageLoader->load($request, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('showOnFaqPage', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addAssociation('media');

        $faqs = $this->faqRepository->search($criteria, $context->getContext());

        return $this->renderStorefront('@WGFaq/storefront/page/faq/index.html.twig', [
            'page' => $page,
            'faqs' => $faqs->getEntities(),
        ]);
    }

    #[Route(
        path: '/faq/{faqId}',
        name: 'frontend.wg.faq.detail',
        methods: ['GET']
    )]
    public function detail(string $faqId, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->genericPageLoader->load($request, $context);

        $criteria = new Criteria([$faqId]);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('media');

        $faq = $this->faqRepository->search($criteria, $context->getContext())->first();

        if (!$faq) {
            throw $this->createNotFoundException('FAQ not found');
        }

        return $this->renderStorefront('@WGFaq/storefront/page/faq/detail.html.twig', [
            'page' => $page,
            'faq' => $faq,
        ]);
    }
}
