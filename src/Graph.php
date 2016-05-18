<?php
/**
 * Class Graph
 * @package Sseidelmann\Typo3DependencyVisualization
 * @author Sebastian Seidelmann <sebastian.seidelmann@googlemail.com>
 */

namespace Sseidelmann\Typo3DependencyVisualization;
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
     * Saves the getopt instance.
     * @var Getopt
     */
    private $getopt;

    /**
     * Constructs the Graph
     */
    public function __construct()
    {
        $this->getopt = new Getopt(array(
            new Option(null, 'extpath', Getopt::REQUIRED_ARGUMENT),
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

            return $options;
        } catch (\Exception $exception) {
            echo sprintf($this->getopt->getBanner(), PHP_EOL . $exception->getMessage() . PHP_EOL);
            exit(0);
        }
    }

    public function generate()
    {
        $options = $this->parseArguments();

        $path = realpath($options['path']) . DIRECTORY_SEPARATOR;

        $files = glob($path . 'wfp2_*/ext_emconf.php');

        print_r($files);


        return;
        $path = realpath(dirname(__FILE__) . '/typo3conf/ext/') . DIRECTORY_SEPARATOR;

        $constraints = array();
        foreach ($files as $file) {
            $pathInfo = pathinfo($file);
            $extName  = @end(explode(DIRECTORY_SEPARATOR, $pathInfo['dirname']));

            $constraints[$extName] = getDepends($extName, $file);
        }


        $graph = new \Fhaculty\Graph\Graph();



        /* @var $graphEdges \Fhaculty\Graph\Vertex[] */
        $graphEdges = array();


        foreach ($constraints as $ext => $depends) {
            $graphEdges[$ext] = $graph->createVertex($ext);
            $graphEdges[$ext]->setAttribute('graphviz.color', getRandColor());
        }



        foreach ($constraints as $ext => $depends) {
            foreach ($depends as $depend => $version) {
                if (!isset($graphEdges[$depend])) {
                    // $graphEdges[$depend] = $graph->createVertex($depend);
                    // $graphEdges[$depend]->setAttribute('graphviz.color', getRandColor());
                }

                if (isset($graphEdges[$depend])) {
                    $edge = $graphEdges[$ext]->createEdgeTo($graphEdges[$depend]);
                    $edge->setAttribute('graphviz.color', $graphEdges[$ext]->getAttribute('graphviz.color'));
                    $edge->setAttribute('splines', 'false');
                }
            }
        }


        $graphviz = new \Graphp\GraphViz\GraphViz();
        $graphviz->display($graph);

        echo $graphviz->createScript($graph);


        function getDepends($extName, $file)
        {
            $_EXTKEY = $extName;
            include $file;

            $config = $EM_CONF[$_EXTKEY];
            if (isset($config['constraints'])) {
                return $config['constraints']['depends'];
            }
            return array();
        }

        function getRandColor()
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

            return $colors[rand(0, count($colors)-1)];
        }
    }
}