# ZKTeco-Machine-Integration-Script


This script is designed to fetch attendance data from a ZKTeco machine and store it in a MySQL database. It uses the [ZKLibrary](https://github.com/kamshory/ZKLibrary) library to communicate with the device.

## Getting Started

### Prerequisites

To run this script, you will need:

- A ZKTeco device that supports the ZK protocol.
- PHP and XAMPP (or similar) installed on your system.
- The ZKLibrary PHP extension.
- A MySQL database to store the attendance data.
- Windows Task Scheduler (or a similar task scheduler) to automate script execution.

### Installation

1. Clone this repository to your local machine:

   ```shell
   git clone https://github.com/faheemmalik11/ZKTeco-Machine-Integration-Script.git
   ```

2. Install the ZKLibrary PHP extension by following the installation instructions provided in the [ZKLibrary repository](https://github.com/kamshory/ZKLibrary).

3. Configure the script:

   - Open `config.php` and update the ZKTeco device IP address, port, and other configuration settings.
   - Update the MySQL database connection details.

4. Set up the Task Scheduler:

   - Create two tasks in the Task Scheduler:
   
     1. Start XAMPP (or your local server) on system startup:
        - Configure a task to start XAMPP when the system boots up.

     2. Run the script:
        - Create a task that runs the script after system startup and then at regular intervals (e.g., every hour).
        - Ensure that the script is executed with the necessary PHP command and script path.

## Usage

1. Start your computer. The XAMPP task will start the local server.
2. The script task will execute your script automatically after system startup and at the specified intervals.
3. The script will fetch attendance data from the ZKTeco machine and store it in the MySQL database.

