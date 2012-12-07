<?php

namespace DotCommerce\FastEntityBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Yaml\Yaml;

class GenerateFormTypeCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dotcommerce:generate:fastentity')
            ->setDescription('')
            ->addArgument('name', InputArgument::REQUIRED, 'A bundle name,  or a class name')
            ->addArgument('field', InputArgument::OPTIONAL, 'The field value shown in the dropdown', 'name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manager = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));

        try {
            /** @var $application \Symfony\Bundle\FrameworkBundle\Console\Application */
            $application = $this->getApplication();
            $bundle      = $application->getKernel()->getBundle($input->getArgument('name'));
            $output->writeln(sprintf('Generating entities for bundle "<info>%s</info>"', $bundle->getName()));
            $metadata = $manager->getBundleMetadata($bundle);
            $dir = realpath($metadata->getPath()."/".str_replace('\\','/',$metadata->getNamespace())."/");
            $bundleName = $bundle->getName();
            $bundleNamespace = $bundle->getNamespace();
        } catch (\InvalidArgumentException $e) {
            $name = strtr($input->getArgument('name'), '/', '\\');
            if (false !== $pos = strpos($name, ':')) {
                $bundleName = substr($name, 0, $pos);
                $name = $this->getContainer()->get('doctrine')->getEntityNamespace(
                    substr($name, 0, $pos)
                ) . '\\' . substr($name, $pos + 1);

            } else {
                throw new \InvalidArgumentException("Please use the format Bundle:Entity");
            }


            if (class_exists($name)) {
                $output->writeln(sprintf('Generating entity "<info>%s</info>"', $name));
                $metadata = $manager->getClassMetadata($name);
            } else {
                throw new \InvalidArgumentException("Couldn't find ".$input->getArgument('name'));
            }
            $dir = realpath($metadata->getPath()."/".str_replace('\\','/',$metadata->getNamespace())."/..");
            $tmp = explode("\\",$metadata->getNamespace());
            array_pop($tmp);
            $bundleNamespace = implode('\\',$tmp);
        }



        foreach ($metadata->getMetadata() as $m) {
            $tmp = explode("\\",$m->getName());
            $entityName = array_pop($tmp);

            // Getting the metadata for the entity class once more to get the correct path if the namespace has multiple occurrences
            try {
                $entityMetadata = $manager->getClassMetadata($m->getName());
            } catch (\RuntimeException $e) {
                // fall back to the bundle metadata when no entity class could be found
                $entityMetadata = $metadata;
            }

            $output->writeln(sprintf('  > generating <comment>%s</comment>', $m->name));
            $identifiers = $entityMetadata->getMetadata()[0]->identifier;
            $count = count($identifiers);
            if($count == 1) {
                $this->generate($identifiers[0],$bundleName,$entityName,$dir, $bundleNamespace, $input->getArgument('field'));
                $this->updateServicesYml($dir, $bundleNamespace,$entityName);
            } elseif($count == 0) {
                $output->writeln(sprintf("No primary key specified for %s", $m->name));
            } else {
                $output->writeln(sprintf("Multiple primary keys are not supported, skipping %s", $m->name));
            }
            //$generator->generate(array($m), $entityMetadata->getPath());
        }
    }

    protected function generate($keyField, $bundleName, $entityName, $dir, $bundleNamespace, $fieldName) {
        $this->renderFile(
            realpath(__DIR__.'/../Resources/'),
            'views/DataTransformer.php.twig',
            $dir."/Form/DataTransformer/".$entityName."Transformer.php",
            array (
                'bundleName' => $bundleName,
                'entityName' => $entityName,
                'bundleNamespace' => $bundleNamespace,
                'fieldName' => $fieldName,
                'keyField' => $keyField,
            )
        );

        $this->renderFile(
            realpath(__DIR__.'/../Resources/'),
            'views/Type.php.twig',
            $dir."/Form/Type/".$entityName."Type.php",
            array (
                'bundleName' => $bundleName,
                'entityName' => $entityName,
                'bundleNamespace' => $bundleNamespace,
                'fieldName' => $fieldName,
                'keyField' => $keyField,
            )
        );
    }

    protected function render($skeletonDir, $template, $parameters)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Filesystem($skeletonDir), array(
            'debug'            => true,
            'cache'            => false,
            'strict_variables' => true,
            'autoescape'       => false,
        ));

        return $twig->render($template, $parameters);
    }

    protected function renderFile($skeletonDir, $template, $target, $parameters)
    {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        return file_put_contents($target, $this->render($skeletonDir, $template, $parameters));
    }

    protected function updateServicesYml( $dir, $bundleNamespace,$entityName ) {
        $servicesFile = $dir."/Resources/config/services.yml";
        $services = array();

        if(file_exists($servicesFile)) {
            $services = Yaml::parse(file_get_contents($servicesFile));
        }

        if (!is_dir(dirname($servicesFile))) {
            mkdir(dirname($servicesFile), 0777, true);
        }

        if(!array_key_exists('services',$services)) {
            $services['services'] = array();
        } elseif(!is_array($services['services'])) {
            throw new \Exception("services.yml - 'services' isn't an array");
        }

        $bundleKey = str_replace('\\','.',strtolower($bundleNamespace."\\form\\type\\".$entityName));
        if(!array_key_exists($bundleKey,$services['services'])) {
            $services['services'][$bundleKey] = array();
            $services['services'][$bundleKey]['class'] = $bundleNamespace."\\Form\\Type\\".$entityName."Type";
            $services['services'][$bundleKey]['arguments'] = array("@doctrine.orm.entity_manager");
            $services['services'][$bundleKey]['tags'] = array (
                array(
                    'name' => 'form.type',
                    'alias' => 'fast'.strtolower($entityName),
                ),
            );
        }

        return file_put_contents($servicesFile,Yaml::dump($services,4));
    }
}