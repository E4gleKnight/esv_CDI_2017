<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        //génération d'un formulaire

        $form = $this->createFormBuilder()
            ->add('file', FileType::class)
            ->add('submit', SubmitType::class)
            ->getForm();
        //gestion de la requête
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){
        //récupération des données du formulaire le file de filetype
            $file = $form["file"]->getData();
        //récupération du contenu csv dans une variable, décoder en tableau associatif
        $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);
        $data = $serializer->decode(file_get_contents($file), 'csv');
        //fonction privée qui injecte le contenu du fichier CSV dans la base de donnée
        $this->saveFileInDatabase($data),
    }
            return $this->render(':default:index.html.twig', [
                "fileform" => $form->createview(),
                "test" => $data ?? []
            ]);
        }

    private function saveFileInDatabase($data)
    {
        
    }

}
