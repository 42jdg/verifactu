<?php
use PHPUnit\Framework\TestCase;
use jdg\Verifactu\VeriFactuRegistroFactura;
use jdg\Verifactu\Models;
use jdg\Verifactu\Listas;

// clear && ./vendor/bin/phpunit tests/VeriFactuRegistroFacturaTest.php
class VeriFactuRegistroFacturaTest extends TestCase
{
    /**
     * Test generating the hash with valid invoice data.
     */
    public function testSend_noCertificate_fail()
    {
        $this->markTestSkipped('Skipping this test to focus in the others.');

        $this->expectException(\SoapFault::class);
        $this->expectExceptionMessage('SOAP-ERROR: Parsing WSDL: Couldn\'t load from \'https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl\' : failed to load external entity "https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl');

        $certificatePath = ''; // '/path/to/your/certificate.pem';
        $passphrase = ''; // 'your_certificate_passphrase';
        list($dsRegistroVeriFactu, $hash, $timestampISO8601) = $this->getSampleData();

        $verifactu = new VeriFactuRegistroFactura();
        $ret = $verifactu->send($dsRegistroVeriFactu, $certificatePath, $passphrase);
        $this->assertTrue($ret['status']=='fail', $ret['response']);
        $this->assertEquals('No se detecta certificado electrónico', $ret['response']);
    }

    /**
     * Test sending an XML fail (certificate with wrong format).
     */
    public function testSend_Certificate_not_valid()
    {
        $certificatePath = __DIR__.'/ACTIVO_EIDAS_CERTIFICADO_PRUEBAS___99999972C.p12';
        $passphrase = '1234';

        $p12File = file_get_contents($certificatePath);
        openssl_pkcs12_read($p12File, $certs, $passphrase);
        $cert = $certs['cert'].$certs['pkey'];
        $tempCertPath = $this->stringToTempFile($cert);
        // $tempCertPath = __DIR__.'/certificado.pem';

        list($dsRegistroVeriFactu, $hash, $timestampISO8601) = $this->getSampleData();

        $verifactu = new VeriFactuRegistroFactura();
        $ret = $verifactu->send($dsRegistroVeriFactu, $tempCertPath, $passphrase);
        if ($ret['status']=='fail') {
            echo "\n -------------- \n";
            print_r($ret['request']);
            echo "\n -------------- \n";
            print_r($ret['response']);
            echo "\n -------------- \n";
        }
        $this->assertTrue($ret['status']=='fail', $ret['response']);
        $this->assertEquals('Codigo[103].NIF no identificado: 99999972C/EIDAS CERTIFICADO PRUEBAS', $ret['response']);

        unlink($tempCertPath);
    }


    private function stringToTempFile(string $certificate): string|false
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cert_');
        if ($tempFile === false) {
            return false;
        }

        if (file_put_contents($tempFile, $certificate) === false) {
            unlink($tempFile); // Elimina el archivo si hay un error
            return false;
        }

        return $tempFile;
    }



    private function getSampleData()
    {
        $hash = '73238806452AEAD89CC3A97DDAF2F5BA986BBC7957CF30E8C888B2F9EBE228A0';
        $timestampISO8601 = '2025-03-22T13:10:37+00:00';
        $obligadoNif = '99999972C'; // K1111111I
        $obligadoNombre = 'EIDAS CERTIFICADO PRUEBAS'; // Obligado S.L.



        $cabecera = new Models\Cabecera();
        $cabecera->ObligadoEmision = new Models\ObligadoEmision($obligadoNif, $obligadoNombre);
        $registroAlta = new Models\RegistroAlta();
        $registroAlta->IDVersion = Listas\L15::V1;
        $registroAlta->IDFactura = new Models\IDFactura();
        $registroAlta->IDFactura->IDEmisorFactura = $obligadoNif;
        $registroAlta->IDFactura->NumSerieFactura = 'A/2025012345';
        $registroAlta->IDFactura->FechaExpedicionFactura = '22-03-2025'; //date('Y-m-d'),
        $registroAlta->NombreRazonEmisor = $obligadoNombre;
        $registroAlta->TipoFactura = Listas\L2::F1;
        $registroAlta->DescripcionOperacion = 'Factura de venta';
        $registroAlta->addDestinatario(new Models\Destinatario('Z5432106V', 'Cliente Físico'));

        $desglose = new Models\Desglose();
        $desglose->ClaveRegimen = Listas\L8A::REGIMEN_GENERAL;
        $desglose->CalificacionOperacion = Listas\L9::S1;
        $desglose->TipoImpositivo = '4';
        $desglose->BaseImponibleOimporteNoSujeto = '10';
        $desglose->CuotaRepercutida = '0.4';
        $registroAlta->addDesglose($desglose);

        $desglose = new Models\Desglose();
        $desglose->ClaveRegimen = Listas\L8A::REGIMEN_GENERAL;
        $desglose->CalificacionOperacion = Listas\L9::S1;
        $desglose->TipoImpositivo = '21';
        $desglose->BaseImponibleOimporteNoSujeto = '100';
        $desglose->CuotaRepercutida = '21';
        $registroAlta->addDesglose($desglose);

        $registroAlta->CuotaTotal = '21.40';
        $registroAlta->ImporteTotal = '131.40';

        $registroAnterior = new Models\RegistroAnterior($obligadoNif,'A/2025012344','22-03-2025','3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60');
        $registroAlta->Encadenamiento = new Models\Encadenamiento($registroAnterior);
        $registroAlta->SistemaInformatico = new Models\SistemaInformatico();
        $registroAlta->SistemaInformatico->NombreRazon = '11111111H';
        $registroAlta->SistemaInformatico->NIF = '11111111H';
        $registroAlta->SistemaInformatico->NombreSistemaInformatico = 'Open Verifactu API';    
        $registroAlta->SistemaInformatico->IdSistemaInformatico = '01';    
        $registroAlta->SistemaInformatico->Version = '1.0.0';   
        $registroAlta->SistemaInformatico->NumeroInstalacion = 'company-01';
        $registroAlta->SistemaInformatico->TipoUsoPosibleSoloVerifactu = Listas\L4::SI; 
        $registroAlta->SistemaInformatico->TipoUsoPosibleMultiOT = Listas\L4::NO;
        $registroAlta->SistemaInformatico->IndicadorMultiplesOT = Listas\L4::NO;

        // For Test only
        $registroAlta->Huella = $hash;
        $registroAlta->FechaHoraHusoGenRegistro = $timestampISO8601;

        $registroFactura = new Models\RegistroFactura($registroAlta);
        $dsRegistroVeriFactu =  new Models\DsRegistroVeriFactu($cabecera, $registroFactura);

        return [$dsRegistroVeriFactu, $hash, $timestampISO8601];
    }
}