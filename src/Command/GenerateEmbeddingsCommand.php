<?php

namespace App\Command;

use LLPhant\Embeddings\DataReader\FileDataReader;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use LLPhant\OllamaConfig;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Ollama\ModelCatalog;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\OllamaApiCatalog;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Loader\TextFileLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;

#[AsCommand(
    name: 'GenerateEmbeddings',
    description: 'Add a short description for your command',
)]
class GenerateEmbeddingsCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws \Exception
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Créer le store (en mémoire pour le test)
        $store = new InMemoryStore();


        $io->title("Hello ! Nous allons générer les embeddings de vos données.");

        // Parse du pdf
        $io->section("Parse du pdf");
        // Parse PDF file and build necessary objects.
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile('public/renseignements.pdf');
        $text = $pdf->getText();

        // 2. Charger un PDF (exemple de loader fictif)
        $io->section("Lecture des données");
        //$loader = new TextFileLoader("../../public/renseignements.pdf");
        //$text = $loader->load(""); // extraction du texte
        //dd($text);
        // 3. Créer le document
        $doc = [];
        $doc[] = new TextDocument(
            id: Uuid::v4(),
            content: $text,
            metadata: new Metadata(['source' => 'pdf'])
        );
        //dd($doc);

        // create embeddings for documents
        $catalog = new ModelCatalog();
        $catalog->getModel('llama3.2:latest');

        // Get model with size variant
        $platform = PlatformFactory::create('http://172.17.0.1:11434', HttpClient::create(), new OllamaApiCatalog(
            'http://172.17.0.1:11434',
            HttpClient::create(),
        ));

        $vectorizer = new Vectorizer($platform, 'llama3.2:latest');

        $indexer = new Indexer(new InMemoryLoader($doc), $vectorizer, $store);

        $indexer->index($doc);
//dd($doc);

        $similaritySearch = new SimilaritySearch($vectorizer, $store);
        $toolbox = new Toolbox([$similaritySearch]);
        $processor = new AgentProcessor($toolbox);
        $agent = new Agent($platform, 'llama3.2:latest', [$processor], [$processor]);

        $messages = new MessageBag(
            Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
            Message::ofUser('Which movie fits the theme of the mafia?')
        );
        $result = $agent->call($messages);

        echo $result->getContent().\PHP_EOL;

        return Command::SUCCESS;
    }
}
