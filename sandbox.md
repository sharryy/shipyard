# **Development Blueprint: PHP Docker Sandbox Package**

## **1\. Project Overview & Philosophy**

**Project Name:** PHP Sandbox (or similar)

**Mission:** To provide PHP developers with an incredibly simple, secure, and fluent API for running code and tasks in isolated Docker containers. The primary goal is to abstract away the complexity of Docker commands and API endpoints, enabling developers to use containers as a simple sandboxing tool without needing to be Docker experts.

**Core Philosophy:**

* **Simplicity First:** The developer experience is paramount. The API should be intuitive, chainable, and read like plain English.  
* **Secure by Default:** The easiest way to run a container should also be the most secure way. This means networking is disabled and resource limits are applied by default unless explicitly overridden.  
* **Object-Oriented:** Docker entities (Containers, Images, etc.) should be represented as first-class PHP objects, not just associative arrays or IDs.  
* **Zero Dependencies (Almost):** The core package should rely only on a robust, well-maintained HTTP client (like Guzzle) for communicating with the Docker Engine API. It should not have a complex dependency tree.

## **2\. Core Architecture & Components**

The package will be architected around a few key classes that separate concerns.

| Class / Component | Responsibility |
| :---- | :---- |
| **Docker** | The main entry point and factory class. It's responsible for managing the connection to the Docker daemon and providing access to resource managers. |
| **ConnectionOptions** | A value object or builder class responsible for holding the configuration for connecting to the Docker daemon (Unix socket, TCP, TLS). |
| **ContainerManager** | Manages the lifecycle of containers: creating, finding, listing, and running them. Implements the fluent builder pattern. |
| **Container** | Represents a single, specific Docker container. Provides methods to interact with that container (start, stop, get logs, remove, etc.). |
| **Exceptions** | A set of custom exceptions (DockerException, ContainerNotFoundException, TimeoutException, etc.) for clear error handling. |

### **Primary Dependency**

* **HTTP Client:** The package will use an HTTP client to communicate with the Docker Engine's REST API. **Guzzle** is the recommended choice due to its robustness and widespread adoption.  
* **Filesystem Management:** A simple, internal utility will be needed to handle the creation and automatic cleanup of temporary directories and files for mounting code into containers.

## **3\. Connection Management**

The Docker class will be instantiated with a ConnectionOptions object. This provides a clear and flexible way to configure the connection.

// Example Usage:  
use YourName\\PhpSandbox\\Docker;  
use YourName\\PhpSandbox\\ConnectionOptions;

// Option 1: Default, most common.  
$docker \= new Docker(ConnectionOptions::fromSocket());

// Option 2: Custom socket path.  
$docker \= new Docker(ConnectionOptions::fromSocket('/home/user/.docker/run/docker.sock'));

// Option 3: Insecure TCP (for local dev environments).  
$docker \= new Docker(ConnectionOptions::fromTcp('tcp://127.0.0.1:2375'));

// Option 4: Secure TCP with TLS client verification.  
$docker \= new Docker(ConnectionOptions::fromTls(  
    'tcp://docker.example.com:2376',  
    '/path/to/certs/ca.pem',  
    '/path/to/certs/cert.pem',  
    '/path/to/certs/key.pem'  
));

## **4\. API Design & Usage Examples**

### **4.1. Main Entry Point: Docker Class**

This class acts as a factory for the resource managers.

$docker \= new Docker(ConnectionOptions::fromSocket());

// Get the manager for containers  
$containerManager \= $docker-\>containers();

// Future managers  
// $imageManager \= $docker-\>images();  
// $volumeManager \= $docker-\>volumes();

### **4.2. ContainerManager: The Core Workflow**

This class is responsible for all container creation and discovery.

#### **A. Sandbox Execution (Run & Forget)**

This is the primary use case for the package. It runs a piece of code in a temporary container and returns the output, handling all cleanup automatically.

// The ContainerManager's \`run\` method provides a shortcut for this pattern.  
$output \= $docker-\>containers()-\>run(  
    image: 'php:8.2-cli',  
    code: '\<?php echo "Hello, World\!"; ?\>',  
    timeout: 10 // in seconds  
);

// $output will contain "Hello, World\!"  
// The container is automatically removed after execution.

#### **B. Building and Managing a Long-Lived Container**

For more complex scenarios, a fluent builder pattern should be used.

// 1\. Get the builder from the manager  
$builder \= $docker-\>containers()-\>from('redis:alpine');

// 2\. Configure the container fluently  
$container \= $builder  
    \-\>withName('my-cache-service')  
    \-\>withCommand(\['redis-server', '--appendonly', 'yes'\])  
    \-\>withPublishedPort(6379, 6379\) // Host port, Container port  
    \-\>withVolume('my-redis-data', '/data') // Mount a named volume  
    \-\>withRestartPolicy('unless-stopped')  
    \-\>withMemoryLimit('256m')  
    \-\>create(); // Creates the container but does not start it

// 3\. Start the container  
$container-\>start();

echo "Created container with ID: " . $container-\>id();

#### **C. Finding Existing Containers**

// Find a specific container by its ID or name  
$container \= $docker-\>containers()-\>find('my-cache-service');

if ($container) {  
    echo "Found container: " . $container-\>name();  
}

// List all containers (returns an array of Container objects)  
$allContainers \= $docker-\>containers()-\>list();

// List with filters  
$runningContainers \= $docker-\>containers()-\>withStatus('running')-\>get();

### **4.3. Container Object: Interacting with an Instance**

Once you have a Container object, you can manage and inspect it.

$container \= $docker-\>containers()-\>find('my-cache-service');

if ($container) {  
    // \--- Lifecycle \---  
    $container-\>stop(timeout: 30); // Optional timeout to wait for graceful stop  
    $container-\>start();  
    $container-\>restart();  
    $container-\>kill();  
    $container-\>remove(); // Optional: \-\>force() to remove a running container

    // \--- Inspection \---  
    $id \= $container-\>id(); // Returns the full ID  
    $name \= $container-\>name();  
    $status \= $container-\>status(); // 'running', 'exited', 'created'  
    $details \= $container-\>details(); // Returns the full JSON array from the 'inspect' API endpoint

    // \--- Interaction \---  
    $logs \= $container-\>logs(); // Gets all logs  
    // Future: $container-\>logs(follow: true) to return a stream

    // Execute a command in the running container  
    $execResult \= $container-\>exec(\['redis-cli', 'ping'\]);  
    // $execResult-\>getOutput() would be "PONG"  
    // $execResult-\>getExitCode() would be 0  
}

## **5\. Error Handling**

The package must use a hierarchy of custom exceptions to allow for robust error handling by the user.

* YourName\\PhpSandbox\\Exception\\DockerException (Base exception)  
  * YourName\\PhpSandbox\\Exception\\ConnectionException (Thrown if connection to daemon fails)  
  * YourName\\PhpSandbox\\Exception\\ContainerNotFoundException  
  * YourName\\PhpSandbox\\Exception\\ProcessTimeoutException (For sandbox runs that exceed their timeout)  
  * YourName\\PhpSandbox\\Exception\\BadRequestException (For API errors, e.g., invalid configuration)

## **6\. Initial composer.json**

{  
    "name": "your-name/php-sandbox",  
    "description": "A simple and secure PHP package to run code in isolated Docker containers.",  
    "type": "library",  
    "license": "MIT",  
    "authors": \[  
        {  
            "name": "Your Name",  
            "email": "your@email.com"  
        }  
    \],  
    "require": {  
        "php": "^8.1",  
        "guzzlehttp/guzzle": "^7.0"  
    },  
    "require-dev": {  
        "phpunit/phpunit": "^10.0"  
    },  
    "autoload": {  
        "psr-4": {  
            "YourName\\\\PhpSandbox\\\\": "src/"  
        }  
    },  
    "autoload-dev": {  
        "psr-4": {  
            "YourName\\\\PhpSandbox\\\\Tests\\\\": "tests/"  
        }  
    }  
}

## **7\. Future Roadmap (Post v1.0)**

* **ImageManager:** Add fluent APIs for building, pulling, and managing images.  
* **VolumeManager & NetworkManager:** Add full support for managing Docker volumes and networks.  
* **Asynchronous Execution:** Integrate with a queue system (like Laravel Queues or Symfony Messenger) to run long-running container tasks in the background.  
* **Log Streaming:** Implement proper handling for streaming container logs in real-time.