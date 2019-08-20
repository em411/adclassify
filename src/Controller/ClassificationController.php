<?php

namespace Adshares\Adclassify\Controller;

use Adshares\Adclassify\Entity\Request as ClassificationRequest;
use Adshares\Adclassify\Repository\AdRepository;
use Adshares\Adclassify\Repository\RequestRepository;
use Adshares\Adclassify\Repository\TaxonomyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClassificationController extends AbstractController
{

    /**
     * @var AdRepository
     */
    private $classificationRepository;

    /**
     * @var RequestRepository
     */
    private $requestRepository;

    /**
     * @var TaxonomyRepository
     */
    private $taxonomyRepository;

    public function __construct(
        AdRepository $classificationRepository,
        RequestRepository $requestRepository,
        TaxonomyRepository $taxonomyRepository
    ) {
        $this->classificationRepository = $classificationRepository;
        $this->requestRepository = $requestRepository;
        $this->taxonomyRepository = $taxonomyRepository;
    }

    public function index(?string $requestId = null): Response
    {
        if ($requestId !== null) {
            if (($request = $this->requestRepository->find($requestId)) === null) {
                throw new NotFoundHttpException(sprintf('Cannot find request #%d', $requestId));
            }
        } else {
            $request = $this->requestRepository->findNextPending();
        }

        $requests = [];
        $prevCampaign = $nextCampaign = null;

        if ($request !== null) {
            $requests = $this->requestRepository->findByCampaign($request);
            $prevCampaign = $this->requestRepository->findNextPending($request, false);
            $nextCampaign = $this->requestRepository->findNextPending($request);
        }

        $categories = $this->taxonomyRepository->getCatgories();

        return $this->render('classification/index.html.twig', [
            'requests' => $requests,
            'campaign' => $request,
            'prevCampaign' => $prevCampaign,
            'nextCampaign' => $nextCampaign,
            'categories' => $categories,
            'categorySafe' => TaxonomyRepository::CATEGORY_SAFE,
        ]);
    }

    public function save(Request $request): Response
    {
        $submittedToken = $request->request->get('token');
        $classifications = $request->request->get('classifications', []);

        if (!$this->isCsrfTokenValid('panel', $submittedToken)) {
            throw new \RuntimeException('Invalid CSRF token');
        }

        $entityManager = $this->getDoctrine()->getManager();
        $taxonomy = array_map(function ($category) {
            return $category['key'];
        }, $this->taxonomyRepository->getCatgories());

        $cRequest = null;
        foreach ($classifications as $id => $categories) {

            /* @var $cRequest ClassificationRequest */
            if (($cRequest = $this->requestRepository->find($id)) === null) {
                throw new \RuntimeException('Invalid classification request id');
            }

            if (isset($categories[TaxonomyRepository::CATEGORY_SAFE])) {
                $category = [TaxonomyRepository::CATEGORY_SAFE];
            } else {
                $category = array_values(array_intersect($taxonomy, array_keys($categories)));
            }

            $cRequest->getAd()->setKeywords(['category' => $category]);
            $cRequest->getAd()->setProcessed(true);
            $entityManager->persist($cRequest->getAd());

            foreach ($this->requestRepository->findByAd($cRequest->getAd()) as $aRequest) {
                /* @var $aRequest ClassificationRequest */
                $aRequest->setStatus(ClassificationRequest::STATUS_PROCESSED);
                $aRequest->setInfo(null);
                $aRequest->setCallbackStatus(ClassificationRequest::CALLBACK_PENDING);
                $aRequest->setSentAt(null);
                $entityManager->persist($aRequest);
            }
        }

        $entityManager->flush();

        $next = $this->requestRepository->findNextPending($cRequest);

        return new RedirectResponse($this->generateUrl('classification', [
            'requestId' => $next ? $next->getId() : null
        ]));
    }

    public function status(Request $request): Response
    {
        $limit = 50;
        $page = max(1, (int)$request->query->get('page', 1));
        $sort = 'id';
        $order = 'desc';

        $requests = $this->requestRepository->findPaginated($limit, ($page - 1) * $limit, $sort, $order);

        return $this->render('classification/status.html.twig', [
            'requests' => $requests,
            'currentPage' => $page,
            'totalPages' => ceil($requests->count() / $limit),
        ]);
    }
}
