<?php
/**
 * Class Graph
 * @package Sseidelmann\Typo3DependencyVisualization
 * @author Sebastian Seidelmann <sebastian.seidelmann@googlemail.com>
 */

namespace Sseidelmann\Typo3DependencyVisualization;
use Fhaculty\Graph\Vertex;
use Graphp\GraphViz\GraphViz;
use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;

/**
 * Class Graph
 * @package Sseidelmann\Typo3DependencyVisualization
 * @author Sebastian Seidelmann <sebastian.seidelmann@googlemail.com>
 */
class Graph
{
    /**
     * Defines the version
     * @var int
     */
    const VERSION = '1.0';

    /**
     * Defines the color of the version.
     * @var string
     */
    const GRAPH_COLOR_VERSION = 'gray';

    /**
     * Saves the getopt instance.
     * @var Getopt
     */
    private $getopt;

    /**
     * Saves the edges.
     * @var Vertex[]
     */
    private $graphNodes = array();

    /**
     * Saves the graph
     * @var \Fhaculty\Graph\Graph
     */
    private $graph;

    /**
     * Saves the graphviz
     * @var GraphViz
     */
    private $graphviz;

    /**
     * Display the typo3 constraints (eg. extbase) in graph
     * @var bool
     */
    private $displayTypoContraints = true;

    /**
     * Saves the options.
     * @var array
     */
    private $options = array();

    /**
     * Constructs the Graph
     */
    public function __construct()
    {
        $this->graph    = new \Fhaculty\Graph\Graph();
        $this->graphviz = new GraphViz();
        $this->getopt   = new Getopt(array(
            new Option(null, 'extpath', Getopt::REQUIRED_ARGUMENT),
            new Option(null, 'extpattern', Getopt::REQUIRED_ARGUMENT),
            new Option(null, 'versions'),
            new Option(null, 'version')
        ));
        $this->getopt->setBanner(implode(PHP_EOL, array(
                "\033[0;32mGraph\033[0m version " . self::VERSION,
                "\033[0;31m%s\033[0m",
                "Usage: graph [options]"
            )) . PHP_EOL);
    }

    /**
     * Parse the arguments.
     * @return array
     */
    private function parseArguments()
    {
        try {
            $this->getopt->parse();
            $options = $this->getopt->getOptions();

            if (isset($options['version'])) {
                echo sprintf($this->getopt->getBanner(), '');
                exit(0);
            }

            if (!isset($options['extpath'])) {
                throw new \Exception('Option \'extpath\' must be given');
            }

            $this->options = $options;
        } catch (\Exception $exception) {
            echo sprintf($this->getopt->getBanner(), PHP_EOL . $exception->getMessage() . PHP_EOL);
            exit(0);
        }
    }

    /**
     * Generate the graph
     * @return void
     */
    public function generate()
    {
        $this->parseArguments();

        $path = realpath($this->options['extpath']) . DIRECTORY_SEPARATOR;
        echo sprintf('Scanning the path %s for extensions', $path) . PHP_EOL;


        $files = $this->getExtensionManagerConfigurationFiles($path);
        $constraints = $this->getConstraints($files);

        $this->createNodesForConstraints($constraints);
        $this->createEdgesForConstraints($constraints);

        if (isset($this->options['versions'])) {
            $this->createVersionConstraints($constraints);
        }


        $this->getGraph()->setAttribute('graphviz.graph.ratio', '0.2');
        $this->getGraphviz()->display($this->getGraph());
    }

    /**
     * Creates the nodes for the constraints.
     * @param array $constraints
     * @return void
     */
    private function createNodesForConstraints(array $constraints)
    {
        foreach (array_keys($constraints) as $ext) {
            $this->createNode($ext);
        }
    }

    private function createVersionConstraints(array $constraints)
    {
        foreach ($constraints as $ext => $depends) {
            foreach ($depends as $depend => $version) {
                $version = ($version == '') ? '*' : $version;
                $versionNodeId = sprintf('%s_%s', $depend, $version);
                if (!$this->findGraphNode($versionNodeId)) {
                    $this->createVersionNode($versionNodeId, sprintf('%s: %s', $depend, $version));
                    $this->setGraphNodeColor($versionNodeId, $this->getGraphNodeColor($depend));
                    $this->createVersionEdge($depend, $versionNodeId);
                }
            }
        }
    }

    /**
     * Creates the node with a name
     * @param string $id
     * @param string $name
     * @return void
     */
    private function createNode($id, $name = null)
    {
        $this->addGraphNode(
            $id,
            $this->getGraph()->createVertex($name !== null ? $name : $id)
        );
    }

    private function createVersionNode($id, $name)
    {
        $this->createNode($id, $name);
        $this->findGraphNode($id)->setAttribute('graphviz.shape', 'rect');
        $this->findGraphNode($id)->setAttribute('graphviz.color', self::GRAPH_COLOR_VERSION);
    }

    /**
     * Create the edges for constraints.
     * @param array $constraints
     * @return void
     */
    private function createEdgesForConstraints(array $constraints)
    {
        foreach ($constraints as $ext => $depends) {
            foreach ($depends as $depend => $version) {
                if (false === $this->findGraphNode($depend) && $this->displayTypoContraints) {
                    $this->createVersionNode($depend, $depend);
                }

                if ($node = $this->findGraphNode($depend)) {
                    $this->createEdge($ext, $depend);
                }
            }
        }
    }

    /**
     * Create the edges from one node to another
     * @param string $from
     * @param string $to
     */
    private function createEdge($from, $to)
    {
        if (($nodeFrom = $this->findGraphNode($from)) !== false &&
            ($nodeTo = $this->findGraphNode($to)) !== false
        ) {
            $edge = $nodeFrom->createEdgeTo($nodeTo);
            $edge->setAttribute('graphviz.color', $this->getGraphNodeColor($from));
            $edge->setAttribute('graphviz.splines', 'false');
        }
    }

    /**
     * Create the edges from one node to another
     * @param string $from
     * @param string $to
     */
    private function createVersionEdge($from, $to)
    {
        if (($nodeFrom = $this->findGraphNode($from)) !== false &&
            ($nodeTo = $this->findGraphNode($to)) !== false
        ) {
            $edge = $nodeFrom->createEdge($nodeTo);
            $edge->setAttribute('graphviz.color', self::GRAPH_COLOR_VERSION);
            $edge->setAttribute('graphviz.splines', 'false');
        }
    }

    /**
     * Returns the files for extension configuration by given ext path.
     * @param string $path
     * @return array
     */
    private function getExtensionManagerConfigurationFiles($path)
    {
        $pattern = isset($this->options['extpattern']) ? $this->options['extpattern'] : '*';
        $path    = sprintf('%s%s/ext_emconf.php', $path, $pattern);

        return glob($path);
    }


    /**
     * Returns the constraints.
     * @param array $files
     * @return array
     */
    private function getConstraints($files)
    {
        $constraints = array();
        foreach ($files as $file) {
            $pathInfo = pathinfo($file);
            $extName = @end(explode(DIRECTORY_SEPARATOR, $pathInfo['dirname']));

            $constraints[$extName] = $this->extractDependencies($extName, $file);
        }

        return $constraints;
    }

    /**
     * Extracts the deps.
     * @param string $extName
     * @param string $file
     * @return array
     */
    private function extractDependencies($extName, $file)
    {
        $_EXTKEY = $extName;
        include $file;

        $config = $EM_CONF[$_EXTKEY];
        if (isset($config['constraints'])) {
            return $config['constraints']['depends'];
        }
        return array();
    }

    private function getRandColor()
    {
        $colors = array(
            'blue',
            'red',
            'coral',
            'crimson',
            'darkslateblue',
            'cornflowerblue',
            'aquamarine1',
            'darkgreen'
        );

        return $colors[rand(0, count($colors) - 1)];
    }


    /**
     * Returns the graph.
     * @return \Fhaculty\Graph\Graph
     */
    private function getGraph()
    {
        return $this->graph;
    }

    /**
     * Returns the graphviz
     * @return GraphViz
     */
    public function getGraphviz()
    {
        return $this->graphviz;
    }

    /**
     * Adds a graph edge.
     * @param string $id
     * @param Vertex $vertex
     */
    private function addGraphNode($id, Vertex $vertex)
    {
        $this->graphNodes[$id] = $vertex;
        $this->setGraphNodeColor($id, $this->getRandColor());
    }

    /**
     * Returns the graph edge.
     * @param string $id
     * @return Vertex|null
     */
    private function findGraphNode($id)
    {
        return isset($this->graphNodes[$id]) ? $this->graphNodes[$id] : false;
    }

    /**
     * Sets the graph color
     * @param string $id
     * @param string $color
     */
    private function setGraphNodeColor($id, $color)
    {
        if ($node = $this->findGraphNode($id)) {
            $node->setAttribute('graphviz.color', $color);
        }
    }

    /**
     * Returns the graph node color.
     * @param string $id
     * @return string
     */
    private function getGraphNodeColor($id)
    {
        if ($node = $this->findGraphNode($id)) {
            return $node->getAttribute('graphviz.color');
        }

        return $this->getRandColor();
    }
}