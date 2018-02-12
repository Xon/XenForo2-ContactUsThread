# ContactUsThread

When the Contact Us form is used, this addon will create a thread in a specified forum.

Associates the thread ownership with the logged in user or as a guest user, and uses a phrase to format the message contents.

The username for guest users must pass the configured username requirements. If they pick a username which is already claimed, it will use the phrase ContactUs_Guest to attempt to make the username unique.

Phrases (used depending on if the user is logged in or not):
- ContactUs_Message_User
- ContactUs_Message_Guest

Variables sent to the phrase:
- username
- subject
- message
- email
- ip