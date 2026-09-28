<?php

namespace App\Controller;

use App\Form\DemoIconType;
use App\Form\Dto\DemoIconDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DemoIconController extends AbstractController
{
    #[Route('/form-icon', name: 'demo_form_icon')]
    public function formIcon(Request $request): Response
    {
        $dto = new DemoIconDto();
        $dto->title = 'Mon bel article';

        $form = $this->createForm(DemoIconType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->render('demo/form_icon_result.html.twig', [
                'title' => $dto->title,
                'dto' => $dto,
            ]);
        }

        return $this->render('demo/form_icon.html.twig', [
            'form' => $form->createView(),
            'domain' => 'avatar',
            'entity_id' => 0,
        ]);
    }
}
