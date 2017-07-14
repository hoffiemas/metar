<?php

// Input, später zu ersetzen durch $get

$Input_ICAO_Airport = "GMAD";
getMetar($Input_ICAO_Airport);

function getMetar($ICAO_Code)
   {

   // Filter um sicherzustellen, dass ein valider code eingegeben wurde

   $opt = array(
      "options" => array(
         "regexp" => "/^[a-zA-Z]{4}$/"
      )
   );
   if (filter_var($ICAO_Code, FILTER_VALIDATE_REGEXP, $opt))
      {
      $ICAO_Code = strtoupper($ICAO_Code);
      $filename = "ftp://tgftp.nws.noaa.gov/data/observations/metar/stations/{$ICAO_Code}.TXT";
      $handle = fopen($filename, "r");
      $contents = fread($handle, filesize($filename));
      fclose($handle);
      echo $contents;
      if (strlen($contents) < 5)
         {
         myerror("Erhaltenes Metar nicht valide!");
         }
        else
         {
         $resRaw = preg_split("/\s+/", $contents);
         array_pop($resRaw);
         $translation = array();
         $translation["airport"] = $resRaw[2]; //ICAO CODE der Station
         $translation["month"] = substr($resRaw[0], 5, 2); //Monat
         $translation["day"] = substr($resRaw[0], 8, 2); // Tag
         $translation["year"] = substr($resRaw[0], 0, 4); // Jahr
         $translation["timezone"] = strtoupper(substr($resRaw[3], 6, 1)); // Z = UTC

         // In US Metars wird angegeben, ob die Werte automatisch erstellt werden, oder eine manuelle
         // Korrektur erfolgt.

         if (strtoupper($resRaw[4]) == "COR" || strtoupper($resRaw[4]) == "AUTO")
            {
            $translation["correctionMode"] = strtoupper($resRaw[4]);
            $i = 5;
            }
           else
            {
            $translation["correctionMode"] = "none";
            $i = 4;
            }

         // nächster Block ist der Wind
         // es gibt im Grunde die folgenden Möglichkeiten
         // a) 00000KMH  bzw. KT bzw. MPS
         // b) VRBdd(d)KMH bzw KT bzw MPS   d= ziffer  VRB = Variabel
         // c) ddddd(d)KMH bzw KT bzw MPS   d= ziffer wobei die ersten 3 immer die Windrichtung sind
         // d) ddddd(d)Gdd(d)KMH bzw KT bzw MPS   d= ziffer wobei die ersten 3 immer die Windrichtung sind

         $wind_raw = $resRaw[$i];
         $i++;

         // untersuche die ersten 5 Digits vom Wind
         // wenn diese 00000 sind, ist es Windstill vgl. a)

         if (strtoupper(substr($wind_raw, 0, 5)) == "00000")
            {
            $translation["windclear"] = "windstill";
            $translation["boolWind"] = false;
            $translation["windfrom_a"] = 0;
            $translation["windfrom_b"] = 0;
            $translation["mainWindspeed"] = 0;

            // die Einheit für den Wind wird dann hier ausgelesen

            $translation["mainWindspeedUnit"] = unitUnifier(trim(substr($wind_raw, 5, 3)));

            // untersuche die ersten 3 Digits auf VRB

            }
         elseif (strtoupper(substr($wind_raw, 0, 3)) == "VRB")
            {
            $translation["windclear"] = "Windrichtung variabel";
            $translation["boolWind"] = true;

            // ansonsten sind die ersten 3 Digits immer die Windrichtung

            }
           else
            {
            $tmpwdirection = strtoupper(substr($wind_raw, 0, 3));
            $translation["windclear"] = "Wind aus {$tmpwdirection} Grad";
            $translation["boolWind"] = true;
            }

         // wenn es also Wind vorhanden = Variante b) oder c) oder d)

         if ($translation["boolWind"])
            {

            // check ob Boeen vorhanden sind

            $pos = strpos(strtoupper($wind_raw) , "G");
            if ($pos !== false)
               {
               $translation["gusts"] = true;
               if ($pos == 5)
                  {
                  $translation["mainWindspeed"] = substr($wind_raw, 3, 2);
                  $translation["windclear"] = $translation["windclear"] . " mit {$translation["mainWindspeed"]}";
                  }
               elseif ($pos == 6)
                  {
                  $translation["mainWindspeed"] = substr($wind_raw, 3, 3);
                  $translation["windclear"] = $translation["windclear"] . " mit {$translation["mainWindspeed"]}";
                  }

               $translation["gustsUpTo"] = strtoupper(substr($wind_raw, $pos + 1, 3));
               if (substr($translation["gustsUpTo"], 2, 1) == "K" || substr($translation["gustsUpTo"], 2, 1) == "M")
                  {
                  $translation["gustsUpTo"] = substr($translation["gustsUpTo"], 0, 2);
                  $translation["mainWindspeedUnit"] = unitUnifier(trim(substr($wind_raw, $pos + 3, 3)));
                  }
                 else
                  {
				  $translation["gustsUpTo"] = substr($translation["gustsUpTo"], 0, 3);
                  $translation["mainWindspeedUnit"] = unitUnifier(trim(substr($wind_raw, $pos + 4, 3)));
                  }
               
			 $translation["windclear"] = $translation["windclear"] . " {$translation["mainWindspeedUnit"]},
			 in Spitzen mit {$translation["gustsUpTo"]} {$translation["mainWindspeedUnit"]}";
              }else
               { //wenn es keine Booen gibt, dann also Variante b oder c
               $translation["gusts"] = false;

               // wenn Wind zweistellig, also an dritter Stelle schon eine Einheit folgt

               if (strtoupper(substr($wind_raw, 5, 1)) == "K" || strtoupper(substr($wind_raw, 5, 1)) == "M")
                  {
                  $translation["mainWindspeed"] = substr($wind_raw, 3, 2);

                  // die Einheit für den Wind wird dann hier ausgelesen

                  $translation["mainWindspeedUnit"] = unitUnifier(trim(substr($wind_raw, 5, 3)));
                  $translation["windclear"] = $translation["windclear"] . " mit {$translation["mainWindspeed"]} {$translation["mainWindspeedUnit"]}";
                  }
                 else
                  {
                  $translation["mainWindspeed"] = substr($wind_raw, 3, 3);

                  // die Einheit für den Wind wird dann hier ausgelesen

                  $translation["mainWindspeedUnit"] = unitUnifier(trim(substr($wind_raw, 6, 3)));
                  $translation["windclear"] = $translation["windclear"] . " mit {$translation["mainWindspeed"]} {$translation["mainWindspeedUnit"]}";
                  }
               }
            }
            
        //falls eine Variabilibiltaet der Windrichtung angegeben wird, wird diese hier ausgewertet
         if (strpos(strtoupper($resRaw[$i]),"V") !== false ){
            $translation["windfrom_a"] = strtoupper(substr($resRaw[$i],0,3));
            $translation["windfrom_b"] = strtoupper(substr($resRaw[$i],4,3));
            $translation["windclear"] = $translation["windclear"] . ", Windrichtung schwankend zwischen {$translation["windfrom_a"]} und {$translation["windfrom_b"]} Grad.";
            $i++;
         }

         var_dump($resRaw);
         var_dump($translation);
         }
      }
     else
      {
      myerror("ICAO-Code nicht valide");
      }
   }

function unitUnifier($str_input)
   {
   if (strtoupper($str_input) == "KT")
      {
      $out = "kt";
      }
   elseif (strtoupper($str_input) == "MPS")
      {
      $out = "m/s";
      }
   elseif (strtoupper($str_input) == "KMH")
      {
      $out = "km/h";
      }

   return $out;
   };

function myerror($msg)
   {
   header("HTTP/1.0 404 Not Found");
   echo $msg;
   die();
   }
?>
