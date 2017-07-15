<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\User;
use AppBundle\Entity\Payment;
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
            $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder(';')]);
            $data = $serializer->decode(file_get_contents($file), 'csv');
            //fonction privée qui injecte le contenu du fichier CSV dans la base de donnée
            // Idéalement, il faudrait un try/catch autour de cette commande qui pourrait lever une exception
            // Dans le cas d'un retour true/false, tester le retour.
            if (!$this->saveFileInDatabase($data)){
            }
        }
        return $this->render(':default:index.html.twig', [
            "fileform" => $form->createview(),
            "test" => $data ?? [],
        ]);
    }

    private function saveFileInDatabase($data)
    {
        // Si une erreur se produit dans le bloc try, une exception sera levée
        // L'execution s'arretera au moment de l'exception, et va dans le catch
        try {
            // EntityManager
            //em gère toutes les actions sur la BDD
            $em = $this->getDoctrine()->getManager();
            $userRepo = $em->getRepository(User::class);
            $paymentRepo = $em->getRepository(Payment::class);

            // Pour chaque ligne
            foreach ($data as $ligne ) {
                // Vérification du bon format de la ligne(des données)
                //On vérifie que la donnée contient le bon nombre de champs avec count
                //On informe quelle donnée est incorrecte avec implode (array to string)
                //var_dump(count($ligne));die();
                if (count($ligne) != 9) {
                    //var_dump($ligne);die();
                    $this->addFlash('danger',  'La ligne ne contient pas le bon nombre de champs ('
                        .implode(';', $ligne).')');
                    continue;
                }
                // findOneBy()
                $user = $userRepo->findOneBy(array(
                    //a gauche on est dans le repo, a droite dans le csv
                    'email' => $ligne["email"],
                ));
                if (!$user) {
                    $user = new User();
                    $user->setEmail($ligne['email']);
                    // Renseigner les autres informations pûrement User
                    $user->setFirstName($ligne['prénom']);
                    $user->setName($ligne['nom']);
                }
                // Si tous les champs d'adresse sont renseignés (not null en base)
                if ($ligne['adresse'] && $ligne['code_postal'] && $ligne['ville']){
                    // Récupérer l'adresse de l'utilisateur
                    //là on récupère l'objet "adress" (la classe) si elle est renseignée
                    $address = $user->getAddress();
                    // Si pas d'adresse
                    if(!$address){
                        //On vérifie si l'adresse est identique à une autre
                        $addressRepo = $em->getRepository(Address::class);
                        //on cherche l'adresse par tous les champs
                        //pour éviter d'encombrer la table adresse
                        //avec des doublons
                        $address = $addressRepo->findOneBy(array(
                            'addressfield' => $ligne['adresse'],
                            'postcode' => $ligne['code_postal'],
                            'city' => $ligne['ville'],
                        ));
                        if (!$address) {
                            $address = new Address();
                        }
                        $user->setAddress($address);
                    }
                    $address->setAddressfield($ligne['adresse'])
                        ->setCity($ligne['ville'])
                        ->setPostcode($ligne['code_postal']);

                    $em->persist($address);
                }
                $payment = $paymentRepo->findOneBy(array(
                    'amount' => $ligne['montant_paiement'],
                    'nature' => $ligne['nature-paiment'],
                    'date' => \DateTime::createFromFormat('d/m/Y', $ligne['date_paiement']),
                    'user' => $user,
                ));

                if (!$payment) {
                    $payment = new Payment();
                    $payment->setAmount($ligne['montant_paiement']);
                    $payment->setDate(\DateTime::createFromFormat('d/m/Y', $ligne['date_paiement']));
                    $payment->setNature($ligne['nature-paiment']);
                    $payment->setUser($user);
                    $em->persist($payment);
                    // $payment->setUser($user)
                    // $user->addPayment($payment)
                }

                $em->persist($user);
                $em->flush();
            }
        } catch (\Exception $e) {
            // Idéalement, on remonte une exception, ici, pour faire simple, on renvoie false
            $this->addFlash('danger', $e->getMessage());
            return false;
        }
        // Si on arrive jusqu'ici, c'est qu'il n'y a pas eu d'exception, on renvoie donc true (en opposition à false)
        // Pas nécessaire si on soulève une exception
        return true;
    }

}
