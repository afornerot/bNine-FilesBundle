<?php

namespace App\Controller;

use App\Form\DemoArticleType;
use App\Form\Dto\DemoArticleDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DemoFormController extends AbstractController
{
    #[Route('/form-demo', name: 'demo_form')]
    public function formDemo(Request $request): Response
    {
        $dto = new DemoArticleDto();
        // Valeurs initiales pour la démo (domaine sample/1 aligné avec /select-demo-single et /select-demo-multiple)
        $dto->title = 'Mon bel article';
        $dto->image = 'sample/1/01-008.jpg';
        $dto->gallery = json_encode([
            'sample/1/01-008.jpg',
            'sample/1/02-030.jpg',
            'sample/1/03-06.jpg',
        ]);

        $form = $this->createForm(DemoArticleType::class, $dto);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $gallery = json_decode($data->gallery ?? '[]', true) ?: [];

            return $this->render('demo/form_demo_result.html.twig', [
                'title' => $data->title,
                'image' => $data->image,
                'gallery' => $gallery,
                'dto' => $data,
            ]);
        }

        return $this->render('demo/form_demo.html.twig', [
            'form' => $form->createView(),
            'domain' => 'sample',
            'entity_id' => 1,
        ]);
    }
}
