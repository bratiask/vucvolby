<?php

namespace bratiask\VucVolby\Command;

use Doctrine\DBAL\Connection;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Nette\Utils\Strings;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Reader;

class GenerateFbImagesCommand extends ContainerAwareCommand
{
    const IMAGES_DIR = __DIR__ . '/../Resources/assets/images/fb/';
    const FONTS_DIR = __DIR__ . '/../Resources/assets/fonts/';

    protected function configure()
    {
        $this->setName('generate-fb-images');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $draw = new ImagickDraw();

        /* Black text */
        $draw->setFillColor('#2f2b20');

        /* Font properties */
        $draw->setFont(self::FONTS_DIR . 'AlfaSlabOne-Regular.ttf');
        $draw->setFontSize(60);
        $draw->setTextEncoding('UTF-8');
        $draw->setTextAlignment(Imagick::ALIGN_CENTER);

        foreach ($this->findAllMunicipalityNames() as $municipality)
        {
            $output->writeln($municipality['name']);
            $this->generateMunicipalityImage($draw, $municipality);
        }
    }

    private function generateMunicipalityImage($draw, $municipality)
    {
        $image = new Imagick();
        $image->readImage(self::IMAGES_DIR . 'blank.png');

        $image_width = $image->getImageWidth();

        $rows = $this->getTextRows($image, $draw, $municipality['name'], $image_width - 160);

        $line_height = 72;
        $top_position = 160;

        foreach ($rows as $i => $row)
        {
            $image->annotateImage($draw, $image_width / 2, $top_position + $line_height * $i, 0, $row);
        }

        $image->annotateImage($draw, $image_width / 2, $top_position + $line_height * count($rows), 0, 'bez kgálikov');

        /* Give image a format */
        $image->setImageFormat('png');

        $image->writeImage(self::IMAGES_DIR . $municipality['municipality_id'] . '-' . Strings::webalize($municipality['name']) . '.png');
    }

    private function getTextRows(Imagick $image, ImagickDraw $draw, $text, $maxWidth)
    {
        $words = explode(" ", $text);

        $lines = array();
        $i=0;
        while ($i < count($words))
        {//as long as there are words

            $line = "";
            do
            {//append words to line until the fit in size
                if($line != ""){
                    $line .= " ";
                }
                $line .= $words[$i];


                $i++;
                if(($i) == count($words)){
                    break;//last word -> break
                }

                //messure size of line + next word
                $linePreview = $line." ".$words[$i];
                $metrics = $image->queryFontMetrics($draw, $linePreview);
                //echo $line."($i)".$metrics["textWidth"].":".$maxWidth."<br>";

            }while($metrics["textWidth"] <= $maxWidth);

            //echo "<hr>".$line."<br>";
            $lines[] = $line;
        }

        //var_export($lines);
        return $lines;
    }

    private function findAllMunicipalityNames()
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                municipality_id,
                name
            FROM
                municipalities");
        $statement->execute();
        return $statement->fetchAll();
    }
}