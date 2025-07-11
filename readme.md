# AB InBev technical test

This test was made for a drupal developer position at abinbev.

The file you are looking for is [here](https://github.com/JorgeRinaldi1995/abinbev-drupal-test/tree/main/web/modules/custom/voting_system) 

## Steps to setup the environment

On main folder run the following comands:

```
lando start
```

```
lando composer install
```

```
lando drush site:install --db-url=mysql://drupal10:drupal10@database/drupal10 -y
```

```
lando drush cr
```

```
lando db-import dump.sql.gz
```

User for test:

login: auth_user
password: 12345

## Simple Voting System Test

### System Description

The simple voting system allows users to vote on questions created by the administrator. The administrator can create questions with titles, identifiers, and answer options. Answer options can include an image, a title, and a brief description.
Users can vote on the created questions, and the administrator can later see the number of votes received for each question, along with the percentage of votes.
The administrator can configure voting to show or hide the total votes for each question after the user votes.
The administrator can configure the voting system to disable the voting system altogether.
The system must also be integrated with Drupal, allowing the votes to be displayed. Furthermore, the system must be able to provide an API so that authorized third-party applications can interact with the polls, allowing, for example, registered polls to be made available in an application with the full Drupal workflow experience.

### Functional Requirements

1. The administrator must be able to register questions with unique titles and identifiers.
2. Each question can have multiple answer options.
3. Answer options can include an image, a title, and a brief description.
4. Users should be able to vote on a question by selecting one of the answer options.
5. The system should record the votes received for each question and answer option.
6. The administrator should be able to view the total number of votes received for each question, along with the vote percentage.
7. The administrator can configure the voting system to be disabled.
8. The system should be integrated with Drupal to display the votes.
9. The administrator should be able to configure whether the vote total for each question should be displayed or hidden in Drupal after the user votes.
10. The system should provide an API so that authorized third-party applications can interact with the registered votes.

### Non-Functional Requirements

1. The system must be easy to use and intuitive for the administrator to register questions and answer options. 
2. The system must be secure, protecting voting data and preventing improper manipulation.
3. 

### Technical Requirements

1. Do not use community modules to solve the problem, except for "restudy."
2. Do not use node for the tasks.
3. The code must be submitted to a GitHub repository, with a database dump and environment via lando.
Note that this test focuses exclusively on the development and operation of the system's backend.
Therefore, the layout, design, or appearance of the user interface (frontend) will not be considered in the evaluation. We are interested in aspects such as:

* Correct implementation of business logic
* Code structure and organization
* Functionality implemented according to requirements
* Appropriate use of technologies and good backend development practices
* Code performance and efficiency