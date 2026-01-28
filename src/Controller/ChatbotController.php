<?php

namespace App\Controller;

use App\Form\ChatbotType;
use LLPhant\Chat\OllamaChat;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use LLPhant\Exception\MissingParameterException;
use LLPhant\OllamaConfig;
use LLPhant\Query\SemanticSearch\QuestionAnswering;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChatbotController extends AbstractController
{
    /**
     * @throws MissingParameterException
     */
    #[Route('/', name: 'app_chatbot')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(ChatbotType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $question = $form->getData()['question'];

            $vectorStore = new FileSystemVectorStore('../documents-vectorStore.json');

            $config = new OllamaConfig();
            $config->model = 'qwen3-embedding:0.6b';
            $config->url="http://172.17.0.1:11434/api/";
            $embeddingGenerator = new OllamaEmbeddingGenerator($config);


            $configChat = new OllamaConfig();
            $configChat->model = 'llama3.2:latest';
            $configChat->url="http://172.17.0.1:11434/api/";

            $qa = new QuestionAnswering(
                $vectorStore,
                $embeddingGenerator,
                new OllamaChat($configChat)
            );

            $answer = $qa->answerQuestion($question);

            return $this->render('chatbot/index.html.twig', [
                'form' => $form,
                'answer' => $answer,
            ]);
        }

        return $this->render('chatbot/index.html.twig', [
            'form' => $form,
        ]);
    }
}
