<?php
// Incluye configuración y protege la acción (solo usuarios autenticados)
require_once '../../config/setup.php';
requireAuth();

// Conexión a la base de datos
$db = new Database();
$conn = $db->connect();

// Arreglo de errores de validación
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanitizar datos del formulario
    $client_id        = isset($_POST['client_id']) ? (int) $_POST['client_id'] : null;
    $first_name       = mb_strtoupper(trim($_POST['first_name'] ?? ''), 'UTF-8');
    $last_name        = mb_strtoupper(trim($_POST['last_name'] ?? ''), 'UTF-8');
    $mother_last_name = mb_strtoupper(trim($_POST['mother_last_name'] ?? ''), 'UTF-8');
    $phone            = trim($_POST['phone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $rfc              = mb_strtoupper(trim($_POST['rfc'] ?? ''), 'UTF-8');
    $address          = mb_strtoupper(trim($_POST['address'] ?? ''), 'UTF-8');
    $tax_regime_id    = isset($_POST['tax_regime_id']) ? (int) $_POST['tax_regime_id'] : null;

    // Validaciones de campos obligatorios y formatos
    if ($first_name === '') {
        $errors['first_name'] = 'El nombre es requerido';
    }
    if ($last_name === '') {
        $errors['last_name'] = 'El apellido paterno es requerido';
    }
    if ($tax_regime_id === null) {
        $errors['tax_regime_id'] = 'El régimen fiscal es requerido';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El email no es válido';
    }
    if ($rfc !== '' && (strlen($rfc) < 12 || strlen($rfc) > 13)) {
        $errors['rfc'] = 'El RFC debe tener 12 o 13 caracteres';
    }

    // Verifica que el régimen fiscal exista en catálogo
    if (empty($errors)) {
        $val = $conn->prepare("SELECT 1 FROM tax_regimes WHERE id = ?");
        $val->execute([$tax_regime_id]);
        if (!$val->fetch()) {
            $errors['tax_regime_id'] = 'Régimen fiscal inválido';
        }
    }

    // Si no hay errores de validación, guarda o actualiza el cliente
    if (empty($errors)) {
        try {
            if ($client_id) {
                // Actualizar cliente existente
                $stmt = $conn->prepare(
                    "UPDATE clients SET
                        first_name       = :first_name,
                        last_name        = :last_name,
                        mother_last_name = :mother_last_name,
                        phone            = :phone,
                        email            = :email,
                        rfc              = :rfc,
                        address          = :address,
                        tax_regime_id    = :tax_regime_id
                     WHERE id = :id"
                );
                $stmt->bindParam(':id', $client_id, PDO::PARAM_INT);
            } else {
                // Insertar nuevo cliente
                $stmt = $conn->prepare("
                    INSERT INTO clients
                      (first_name, last_name, mother_last_name, phone, email, rfc, address, tax_regime_id)
                    VALUES
                      (:first_name, :last_name, :mother_last_name, :phone, :email, :rfc, :address, :tax_regime_id)
                ");
            }
            // Bindeo común de parámetros
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':mother_last_name', $mother_last_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':rfc', $rfc);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':tax_regime_id', $tax_regime_id, PDO::PARAM_INT);

            // Ejecuta el guardado y redirige según éxito y tipo de operación
            if ($stmt->execute()) {
                if ($client_id) {
                    $_SESSION['success_message'] = 'Cliente actualizado correctamente';
                    redirect('/swift_invoice/modules/clients/edit.php?id=' . $client_id);
                } else {
                    $_SESSION['success_message'] = 'Cliente creado correctamente';
                    redirect('/swift_invoice/modules/clients/create.php');
                }
            } else {
                throw new Exception('Error al guardar el cliente. Intente nuevamente.');
            }
        } catch (PDOException $e) {
            // Maneja errores por claves únicas duplicadas (RFC, email)
            if ($e->getCode() == 23000) {
                $_SESSION['client_save_error'] = 'El email, RFC o datos duplicados ya están registrados';
            } else {
                $_SESSION['client_save_error'] = 'Error en la base de datos: ' . $e->getMessage();
            }
            redirect(
                $client_id
                ? '/swift_invoice/modules/clients/edit.php?id=' . $client_id
                : '/swift_invoice/modules/clients/create.php'
            );
        } catch (Exception $e) {
            // Otro tipo de error (por ejemplo, error de ejecución)
            $_SESSION['client_save_error'] = $e->getMessage();
            redirect(
                $client_id
                ? '/swift_invoice/modules/clients/edit.php?id=' . $client_id
                : '/swift_invoice/modules/clients/create.php'
            );
        }
    }

    // Si hay errores de validación, guarda errores y datos en sesión para repoblar formulario
    if (!empty($errors)) {
        $_SESSION['client_form_errors'] = $errors;
        $_SESSION['client_form_data'] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'mother_last_name' => $mother_last_name,
            'phone' => $phone,
            'email' => $email,
            'rfc' => $rfc,
            'address' => $address,
            'tax_regime_id' => $tax_regime_id
        ];
        redirect(
            $client_id
            ? '/swift_invoice/modules/clients/edit.php?id=' . $client_id
            : '/swift_invoice/modules/clients/create.php'
        );
    }
} else {
    // Si no viene por POST, redirige al listado
    redirect('/swift_invoice/modules/clients/');
}
